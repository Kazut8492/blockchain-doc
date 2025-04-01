<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Document;
use App\Services\BlockchainService;
use Illuminate\Support\Facades\Auth;

class ChunkedUploadController extends Controller
{
    protected $blockchainService;
    
    public function __construct(BlockchainService $blockchainService)
    {
        $this->blockchainService = $blockchainService;
    }
    
    /**
     * Process a file chunk upload
     */
    public function upload(Request $request)
    {
        $request->validate([
            'chunk' => 'required',
            'index' => 'required|integer',
            'totalChunks' => 'required|integer',
            'filename' => 'required|string',
            'chunkId' => 'required|string',
        ]);
        
        $chunkId = $request->input('chunkId');
        $index = $request->input('index');
        $totalChunks = $request->input('totalChunks');
        $filename = $request->input('filename');
        
        try {
            // チャンクの保存先ディレクトリ
            $chunkStoragePath = 'chunks/' . $chunkId;
            $fullChunkDirPath = storage_path('app/private/' . $chunkStoragePath);
            
            // ディレクトリが存在することを確認
            if (!file_exists($fullChunkDirPath)) {
                mkdir($fullChunkDirPath, 0755, true);
            }
            
            // チャンクファイルを保存（Laravel のストレージ機能を使用）
            $chunkPath = $chunkStoragePath . '/' . $index;
            $saved = $request->file('chunk')->storeAs('', $chunkPath, 'local');
            
            if (!$saved) {
                throw new \Exception("Failed to save chunk to {$chunkPath}");
            }
            
            Log::info("Chunk successfully saved to: {$chunkPath}");
            
            // 全てのチャンクがアップロードされたか確認
            $uploadedChunks = count(Storage::files($chunkStoragePath));
            
            if ($uploadedChunks == $totalChunks) {
                // 全チャンクが揃った場合、マージ処理を行う
                $finalFilename = time() . '_' . $filename;
                $finalStoragePath = 'private/documents';
                $finalPath = $finalStoragePath . '/' . $finalFilename;
                $fullFinalPath = storage_path('app/' . $finalPath);
                
                // 保存先ディレクトリを確認
                $finalDir = dirname($fullFinalPath);
                if (!file_exists($finalDir)) {
                    mkdir($finalDir, 0755, true);
                }
                
                // ファイルを開いて書き込みモードに
                $targetFile = fopen($fullFinalPath, 'wb');
                
                if (!$targetFile) {
                    throw new \Exception("Failed to create target file: {$fullFinalPath}");
                }
                
                // ハッシュ計算のための準備
                $hashContext = hash_init('sha512');
                
                // 全チャンクを順番に結合
                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunkFilePath = storage_path('app/private/' . $chunkStoragePath . '/' . $i);
                    
                    Log::info("Processing chunk at: {$chunkFilePath}");
                    
                    if (!file_exists($chunkFilePath)) {
                        Log::error("Chunk file not found: {$chunkFilePath}");
                        throw new \Exception("Chunk file not found: {$chunkFilePath}");
                    }
                    
                    $chunkContent = file_get_contents($chunkFilePath);
                    
                    if ($chunkContent === false) {
                        throw new \Exception("Failed to read chunk content from: {$chunkFilePath}");
                    }
                    
                    // ファイルへの書き込みとハッシュ更新
                    fwrite($targetFile, $chunkContent);
                    hash_update($hashContext, $chunkContent);
                }
                
                fclose($targetFile);
                $hash = hash_final($hashContext);
                
                Log::info('Document hash calculated: ' . $hash);
                
                // 既存のドキュメントをチェック
                $existingDocument = Document::where('hash', $hash)->first();
                if ($existingDocument) {
                    // 一時ディレクトリのクリーンアップ
                    Storage::deleteDirectory($chunkStoragePath);
                    
                    return response()->json([
                        'message' => 'This document has already been registered',
                        'document' => $existingDocument
                    ], 400);
                }
                
                // ドキュメントレコードの作成
                $document = new Document();
                $document->filename = $finalFilename;
                $document->original_filename = $filename;
                $document->mime_type = 'application/pdf';
                $document->size = filesize($fullFinalPath);
                $document->hash = $hash;
                $document->path = $finalPath;
                $document->user_id = null; // デモ/テスト用
                $document->blockchain_status = 'pending';
                $document->blockchain_network = config('blockchain.network_name', 'Sepolia Testnet');
                $document->save();
                
                Log::info('Document saved with hash: ' . $hash);
                
                // ブロックチェーンに登録
                $registered = $this->blockchainService->registerDocument($document);
                
                if (!$registered) {
                    // ブロックチェーン登録に失敗した場合でもドキュメントは保持するが、失敗としてマーク
                    $document->blockchain_status = 'failed';
                    $document->save();
                    
                    Log::error('Failed to register document on blockchain: ' . $hash);
                }
                
                // 一時ディレクトリのクリーンアップ (すべての処理が完了した後)
                Storage::deleteDirectory($chunkStoragePath);
                
                return response()->json([
                    'message' => 'Document uploaded and blockchain registration initiated',
                    'document' => $document
                ], 201);
            }
            
            return response()->json([
                'message' => 'Chunk uploaded successfully',
                'chunksUploaded' => $uploadedChunks,
                'totalChunks' => $totalChunks
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error processing chunks: ' . $e->getMessage());
            // デバッグ目的でエラー時にはチャンクを削除しない
            // Storage::deleteDirectory($chunkStoragePath);
            return response()->json([
                'message' => 'Error uploading document: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check if all chunks have been uploaded
     */
    private function allChunksUploaded($chunkId, $totalChunks)
    {
        $uploadedChunks = count(Storage::files("private/chunks/{$chunkId}"));
        return $uploadedChunks == $totalChunks;
    }
    
    /**
     * Merge all chunks into the final file
     */
    private function mergeChunks($chunkId, $totalChunks, $finalPath)
    {
        // ディレクトリが存在することを確認
        $finalDirectory = dirname(storage_path('app/' . $finalPath));
        if (!file_exists($finalDirectory)) {
            mkdir($finalDirectory, 0755, true);
        }
        
        // 最終ファイルを作成
        $handle = fopen(storage_path('app/' . $finalPath), 'wb');
        
        // 全チャンクを順番に結合
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFilePath = storage_path('app/private/chunks/' . $chunkId . '/' . $i);
            Log::info('Looking for chunk at: ' . $chunkFilePath);
            Log::info('File exists: ' . (file_exists($chunkFilePath) ? 'Yes' : 'No'));
            
            if (file_exists($chunkFilePath)) {
                $chunkContent = file_get_contents($chunkFilePath);
                fwrite($handle, $chunkContent);
            } else {
                Log::error('Chunk file not found: ' . $chunkFilePath);
            }
        }
        
        fclose($handle);
        return true;
    }
}