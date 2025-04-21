// components/UploadDocument.jsx (Updated version)
import React, { useState, useRef, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import axios from 'axios';

const UploadDocument = () => {
  // Get entryId from URL query params
  const location = useLocation();
  const queryParams = new URLSearchParams(location.search);
  const entryId = queryParams.get('entryId');
  
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [step, setStep] = useState('upload'); // 'upload' or 'preview'
  const [uploadStatus, setUploadStatus] = useState('pending'); // 'pending', 'uploading', 'success', 'error'
  const [uploadProgress, setUploadProgress] = useState(0);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [errorMessage, setErrorMessage] = useState('');
  const [entryData, setEntryData] = useState(null);
  const [metadata, setMetadata] = useState({
    storageYears: 10,
    accountingPeriod: '本決算'
  });
  
  const fileInputRef = useRef(null);
  const navigate = useNavigate();
  
  // Fetch entry data
  useEffect(() => {
    const fetchEntryData = async () => {
      if (!entryId) {
        setError('No entry ID provided. Please create an entry first.');
        return;
      }
      
      try {
        const response = await axios.get(`/api/fiscal-entries/${entryId}`);
        setEntryData(response.data);
        
        // Update metadata with entry data
        setMetadata(prev => ({
          ...prev,
          accountingPeriod: response.data.fiscal_period
        }));
      } catch (err) {
        console.error('Error fetching entry data:', err);
        setError('Error fetching entry data: ' + (err.response?.data?.message || err.message));
      }
    };
    
    fetchEntryData();
  }, [entryId]);
  
  // Handle drag events
  const handleDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
  };
  
  const handleDrop = (e) => {
    e.preventDefault();
    e.stopPropagation();
    
    const droppedFile = e.dataTransfer.files[0];
    processFile(droppedFile);
  };

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    processFile(selectedFile);
  };

  const processFile = (newFile) => {
    // Clear previous file and error
    setError('');
    setErrorMessage('');
    
    if (!newFile) return;
    
    if (newFile.type !== 'application/pdf') {
      setError('Please select a valid PDF file');
      return;
    }
    
    // Initialize file with metadata
    setFile(newFile);
    setUploadStatus('pending');
    setUploadProgress(0);
    
    // Generate preview URL
    const reader = new FileReader();
    reader.onload = (e) => {
      setPreviewUrl(e.target.result);
    };
    reader.readAsDataURL(newFile);
  };

  const handleRemoveFile = () => {
    setFile(null);
    setPreviewUrl(null);
    setError('');
    setErrorMessage('');
    setUploadStatus('pending');
    setUploadProgress(0);
    
    // Reset file input
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };
  
  const handleStorageYearsChange = (value) => {
    setMetadata(prev => ({
      ...prev,
      storageYears: value
    }));
  };

  const handleAccountingPeriodChange = (value) => {
    setMetadata(prev => ({
      ...prev,
      accountingPeriod: value
    }));
  };

  const handleGoToPreview = () => {
    if (!file) {
      setError('Please add a PDF file');
      return;
    }
    setStep('preview');
  };

  const handleBackToUpload = () => {
    setStep('upload');
  };

  const handleUpload = async () => {
    if (!file) {
      setError('Please select a PDF file');
      return;
    }
    
    if (!entryId) {
      setError('No entry ID provided. Please create an entry first.');
      return;
    }
    
    setLoading(true);
    setError('');
    setErrorMessage('');
    setUploadStatus('uploading');
    setUploadProgress(0);
    
    const formData = new FormData();
    formData.append('document', file);
    formData.append('fiscal_entry_id', entryId);
    formData.append('storageYears', metadata.storageYears);
    formData.append('accountingPeriod', metadata.accountingPeriod);
    
    try {
      // Configure for upload progress
      const config = {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          setUploadProgress(percentCompleted);
        },
        timeout: 30000 // 30 second timeout
      };
      
      const response = await axios.post('/api/documents', formData, config);
      
      // Update status to success
      setUploadStatus('success');
      
      // Navigate to home after successful upload
      setTimeout(() => {
        navigate('/');
      }, 1500); // Short delay to show success message
      
    } catch (err) {
      console.error('Error uploading document:', err);
      
      // Update status to error with more detailed error message
      setUploadStatus('error');
      
      // Provide more specific error message based on error type
      let message = 'Error uploading document';
      
      if (err.code === 'ECONNABORTED') {
        message = 'Upload timeout - server took too long to respond';
      } else if (err.response) {
        // Server responded with error
        if (err.response.status === 413) {
          message = 'File too large';
        } else if (err.response.data?.message) {
          message = err.response.data.message;
        } else {
          message = `Server error (${err.response.status})`;
        }
      } else if (err.request) {
        // Request made but no response
        message = 'No response from server';
      }
      
      setErrorMessage(message);
      setError('File upload failed. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  if (!entryId) {
    return (
      <div className="container mx-auto px-4 py-8">
        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm text-yellow-700">
                No entry ID provided. Please create an entry first.
                <button 
                  onClick={() => navigate('/create-entry')}
                  className="font-medium underline text-yellow-700 hover:text-yellow-600 ml-2"
                >
                  Create Entry
                </button>
              </p>
            </div>
          </div>
        </div>
      </div>
    );
  }
  
  if (!entryData && !error) {
    return <div className="container mx-auto px-4 py-8">Loading entry data...</div>;
  }

  // Render upload interface
  const renderUploadSection = () => (
    <div className="w-full max-w-4xl mx-auto">
      <h1 className="text-2xl font-semibold text-gray-800 mb-2">Upload Document</h1>
      <p className="text-gray-600 mb-2">Upload a PDF document for the following entry:</p>
      
      {entryData && (
        <div className="bg-blue-50 p-4 rounded-md mb-6">
          <p><strong>Fiscal Year:</strong> {entryData.fiscal_year}</p>
          <p><strong>Fiscal Period:</strong> {entryData.fiscal_period}</p>
          <p><strong>Document Type:</strong> {entryData.document_type}</p>
          <p><strong>Creator:</strong> {entryData.creator}</p>
        </div>
      )}
      
      {error && (
        <div className="bg-red-50 text-red-600 px-4 py-3 rounded-md mb-4 border-l-4 border-red-500">
          {error}
        </div>
      )}
      
      {!file ? (
        <div 
          className="border-2 border-dashed border-gray-300 rounded-lg p-10 text-center bg-gray-50 hover:bg-blue-50 hover:border-blue-500 transition-colors cursor-pointer mb-6"
          onDragOver={handleDragOver}
          onDrop={handleDrop}
          onClick={() => fileInputRef.current.click()}
        >
          <div className="flex flex-col items-center">
            <svg className="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <button className="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md shadow-sm font-medium text-sm transition-colors mb-3">
              ファイルを選択
            </button>
            <p className="text-gray-500 text-sm">またはファイルをドラッグ＆ドロップ</p>
          </div>
          <input 
            type="file" 
            ref={fileInputRef}
            accept="application/pdf" 
            onChange={handleFileChange} 
            className="hidden"
          />
        </div>
      ) : (
        <div className="border border-gray-200 rounded-lg p-4 bg-white shadow-sm mb-6">
          <div className="flex items-center gap-3 mb-4">
            <div className="text-gray-600">
              <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div className="flex-1 text-gray-800 text-sm font-medium break-all">
              {file.name}
            </div>
            <button 
              className="text-gray-400 hover:text-red-500 hover:bg-red-50 p-1 rounded-full transition-colors"
              onClick={handleRemoveFile}
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
              </svg>
            </button>
          </div>
          
          <div className="flex flex-wrap gap-4">
            <div className="flex items-center gap-2">
              <label className="text-sm text-gray-600">保存期間：</label>
              <select 
                value={metadata.storageYears} 
                onChange={(e) => handleStorageYearsChange(e.target.value)}
                className="border border-gray-300 rounded py-2 px-3 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10].map(year => (
                  <option key={year} value={year}>{year}年間</option>
                ))}
              </select>
            </div>
          </div>
        </div>
      )}

      <div className="flex justify-center mt-8">
        <button 
          className={`px-6 py-3 rounded-md font-medium min-w-[200px] ${
            !file 
              ? 'bg-gray-400 cursor-not-allowed' 
              : 'bg-blue-500 hover:bg-blue-600 text-white shadow-sm'
          }`}
          onClick={handleGoToPreview}
          disabled={!file}
        >
          プレビュー
        </button>
      </div>
    </div>
  );

  // Render preview interface
  const renderPreviewSection = () => (
    <div className="w-full max-w-4xl mx-auto">
      <div className="flex items-center gap-4 mb-6">
        <button 
          className="text-blue-500 hover:text-blue-600 flex items-center gap-2 focus:outline-none" 
          onClick={handleBackToUpload}
        >
          <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          前のページに戻る
        </button>
        <h1 className="text-2xl font-semibold text-gray-800">プレビュー</h1>
      </div>
      
      {error && (
        <div className="bg-red-50 text-red-600 px-4 py-3 rounded-md mb-4 border-l-4 border-red-500 flex items-center justify-between">
          <div className="flex items-center">
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            {error}
          </div>
          <button 
            onClick={() => setError('')}
            className="text-red-400 hover:text-red-600"
          >
            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>
      )}
      
      <div className="max-w-md mx-auto border border-gray-200 rounded-lg p-4 bg-white shadow-sm mb-8">
        <div className="text-lg font-semibold text-gray-800 mb-3">
          {entryData?.fiscal_year} {entryData?.fiscal_period}
        </div>
        <div className="flex justify-between items-center mb-4">
          <div className="bg-gray-100 px-3 py-1 rounded text-sm text-gray-600 font-medium">
            {entryData?.document_type}
          </div>
          <div className="flex items-center gap-2 text-sm text-gray-600">
            <span>保存期間</span>
            <input 
              type="text" 
              value={metadata.storageYears} 
              readOnly 
              className="w-10 border border-gray-300 rounded px-2 py-1 text-center bg-gray-50"
            />
            <span>年</span>
          </div>
        </div>
        
        <div className="border border-gray-200 rounded bg-gray-50 aspect-[3/4] overflow-hidden">
          {previewUrl ? (
            <embed 
              src={previewUrl} 
              type="application/pdf"
              className="w-full h-full"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-gray-500 text-sm">
              Loading preview...
            </div>
          )}
        </div>
      </div>
      
      <div className="flex justify-center mt-8">
        <button 
          className={`px-6 py-3 rounded-md font-medium min-w-[200px] ${
            loading 
              ? 'bg-gray-400 cursor-not-allowed' 
              : 'bg-blue-500 hover:bg-blue-600 text-white shadow-sm'
          }`}
          onClick={handleUpload}
          disabled={loading}
        >
          {loading ? 'アップロード中...' : '確認'}
        </button>
      </div>
    </div>
  );

  // Render upload progress
  const renderUploadProgress = () => (
    <div className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 py-4 px-6 shadow-md z-20">
      <div className="max-w-md mx-auto">
        <div className="flex items-center gap-4 py-3">
          <div className="text-gray-600">
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
          </div>
          <div className="flex-1">
            <div className="text-sm text-gray-800 font-medium">
              {file?.name}
            </div>
            {uploadStatus === 'uploading' && (
              <div className="mt-2">
                <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                  <div 
                    className="h-full bg-blue-500 transition-all duration-300" 
                    style={{ width: `${uploadProgress}%` }}
                  ></div>
                </div>
                <div className="text-xs text-gray-500 mt-1">
                  {uploadProgress}% completed
                </div>
              </div>
            )}
            {uploadStatus === 'success' && (
              <div className="text-green-600 text-sm mt-1 flex items-center gap-2">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path>
                </svg>
                アップロード完了
              </div>
            )}
            {uploadStatus === 'error' && (
              <div className="flex flex-col mt-1">
                <div className="flex justify-between items-center">
                  <div className="text-red-600 text-sm font-medium flex items-center">
                    <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    アップロード失敗
                  </div>
                  <div className="flex">
                    <button 
                      className="text-blue-500 hover:text-blue-700 p-1 mr-1"
                      onClick={handleUpload}
                      title="Retry upload"
                    >
                      <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                      </svg>
                    </button>
                  </div>
                </div>
                {errorMessage && (
                  <div className="text-xs text-red-500 mt-1">
                    {errorMessage}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );

  return (
    <div className="container mx-auto px-4 py-6">
      {step === 'upload' && renderUploadSection()}
      {step === 'preview' && renderPreviewSection()}
      {loading && renderUploadProgress()}
    </div>
  );
};

export default UploadDocument;