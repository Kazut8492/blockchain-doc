<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SystemController extends Controller
{
    /**
     * Get the maximum upload file size based on PHP settings
     */
    public function getMaxUploadSize()
    {
        $upload_max_filesize = $this->parseSize(ini_get('upload_max_filesize'));
        $post_max_size = $this->parseSize(ini_get('post_max_size'));
        $max_upload_size = min($upload_max_filesize, $post_max_size);
        
        return response()->json([
            'max_upload_size' => $max_upload_size,
            'max_upload_size_formatted' => $this->formatSize($max_upload_size),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ]);
    }
    
    /**
     * Convert PHP size string to bytes
     */
    private function parseSize($size)
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        return round($size);
    }
    
    /**
     * Format bytes to human readable size
     */
    private function formatSize($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}