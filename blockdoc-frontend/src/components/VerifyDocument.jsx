import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';

const VerifyDocument = () => {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [verificationResult, setVerificationResult] = useState(null);
  const [progress, setProgress] = useState(0);
  const [useChunks, setUseChunks] = useState(false);
  const [systemMaxUploadSize, setSystemMaxUploadSize] = useState(0);
  const navigate = useNavigate();

  useEffect(() => {
    // Get the system's maximum upload size on component mount
    const fetchMaxUploadSize = async () => {
      try {
        const response = await axios.get('api/system/max-upload-size');
        const maxBytes = response.data.max_upload_size;
        setSystemMaxUploadSize(maxBytes);
      } catch (err) {
        console.error('Failed to get max upload size, defaulting to 2MB');
        setSystemMaxUploadSize(2 * 1024 * 1024); // Default to 2MB if server doesn't provide info
      }
    };

    fetchMaxUploadSize();
  }, []);

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile && selectedFile.type === 'application/pdf') {
      setFile(selectedFile);
      setError('');
      setVerificationResult(null);
      
      // Check if we need to use chunked upload based on file size
      if (selectedFile.size > systemMaxUploadSize && systemMaxUploadSize > 0) {
        setUseChunks(true);
        console.log(`File size ${selectedFile.size} exceeds system limit ${systemMaxUploadSize}. Using chunked upload.`);
      } else {
        setUseChunks(false);
      }
    } else {
      setFile(null);
      setError('Please select a valid PDF file');
    }
  };

  const verifyWithChunks = async (file) => {
    setLoading(true);
    setProgress(0);
    setError('');
    setVerificationResult(null);
    
    const chunkSize = 1024 * 1024; // 1MB chunks
    const totalChunks = Math.ceil(file.size / chunkSize);
    const chunkId = Date.now().toString();
    
    try {
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize;
            const end = Math.min(file.size, start + chunkSize);
            const chunk = file.slice(start, end);
            
            const formData = new FormData();
            formData.append('chunk', chunk);
            formData.append('index', i);
            formData.append('totalChunks', totalChunks);
            formData.append('filename', file.name);
            formData.append('chunkId', chunkId);
            
            console.log(`Sending chunk ${i+1}/${totalChunks} to /api/verify-chunk`);
            
            // Call the verify-chunk endpoint instead of upload-chunk
            const response = await axios.post('api/verify-chunk', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            console.log(`Chunk ${i+1} response:`, response.data);
            
            // Update progress
            const newProgress = Math.round(((i + 1) / totalChunks) * 100);
            setProgress(newProgress);
            
            // Check if this response contains verification results
            // (should be the case for the last chunk)
            if (response.data.verified !== undefined) {
                console.log('Verification result received:', response.data);
                setVerificationResult(response.data);
                setLoading(false);
                return;
            }
            
            // If it's not the last chunk and we just got a status update, continue
            if (response.data.status === 'chunk_received' && i < totalChunks - 1) {
                continue;
            }
        }
        
        // If we've processed all chunks but didn't get a verification result,
        // show an error (this shouldn't happen with proper backend handling)
        setError('Completed processing all chunks but did not receive verification result');
        setLoading(false);
    } catch (err) {
        console.error('Error during chunked verification:', err);
        setLoading(false);
        setError(err.response?.data?.message || 'Error verifying document');
    }
  };

  const verifyWithRegularUpload = async (file) => {
    setLoading(true);
    setError('');
    setVerificationResult(null);

    const formData = new FormData();
    formData.append('document', file);

    try {
      const response = await axios.post('api/verify', formData, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
      
      setVerificationResult(response.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Error verifying document');
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      setError('Please select a PDF file to verify');
      return;
    }

    if (useChunks) {
      await verifyWithChunks(file);
    } else {
      await verifyWithRegularUpload(file);
    }
  };

  return (
    <div className="verify-document">
      <h1>Verify Document</h1>
      <p>Upload a PDF document to verify if it has been registered on the blockchain</p>
      
      {error && <div className="error-message">{error}</div>}
      
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="document">Select PDF File to Verify</label>
          <input 
            type="file" 
            id="document" 
            accept="application/pdf" 
            onChange={handleFileChange} 
          />
          {file && file.size > 2 * 1024 * 1024 && (
            <div className="file-size-notice">
              Large file detected ({(file.size / (1024 * 1024)).toFixed(2)} MB)
              {useChunks && <span> - Will use chunked upload</span>}
            </div>
          )}
        </div>
        
        <button 
          type="submit" 
          className="btn-primary" 
          disabled={loading || !file}
        >
          {loading ? 'Verifying...' : 'Verify Document'}
        </button>
      </form>
      
      {loading && (
        <div className="upload-progress">
          <div className="progress-bar">
            <div 
              className="progress-bar-fill" 
              style={{ width: `${progress}%` }}
            ></div>
          </div>
          <div className="progress-text">{progress}% processed</div>
        </div>
      )}
      
      {verificationResult && (
        <div className={`verification-result ${verificationResult.verified ? 'verified' : 'not-verified'}`}>
          <h2>{verificationResult.verified ? 'Document Verified!' : 'Document Not Verified'}</h2>
          
          {verificationResult.verified ? (
            <div className="verification-details">
              <p>This document has been verified on the blockchain.</p>
              <p>Document Hash: {verificationResult.hash}</p>
              <p>Registered on: {new Date(verificationResult.timestamp).toLocaleString()}</p>
              <p>Transaction Hash: {verificationResult.transaction_hash}</p>
              <a 
                href={`https://sepolia.etherscan.io/tx/${verificationResult.transaction_hash}`} 
                target="_blank" 
                rel="noopener noreferrer"
                className="etherscan-link"
              >
                View on Etherscan
              </a>
              
              {verificationResult.warning && (
                <div className="warning-message">
                  Warning: {verificationResult.warning}
                </div>
              )}
            </div>
          ) : (
            <div className="verification-details">
              <p>{verificationResult.message}</p>
              <p>Document Hash: {verificationResult.hash}</p>
              
              {verificationResult.status === 'pending' && (
                <p>Your document is being processed. Please check back later.</p>
              )}
              
              {verificationResult.status === 'failed' && (
                <p>Document registration failed. Please try uploading again.</p>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default VerifyDocument;