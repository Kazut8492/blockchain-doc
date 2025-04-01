import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

const UploadDocument = () => {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
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

  // チャンクアップロード処理を簡素化（認証なし）
  const uploadWithChunks = async (file) => {
    setLoading(true);
    setProgress(0);
    setError('');
    
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
            
            const response = await axios.post('api/upload-chunk', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            // 進捗更新
            const newProgress = Math.round(((i + 1) / totalChunks) * 100);
            setProgress(newProgress);
            
            // 最終チャンクのレスポンスを処理
            if (response.data.document) {
                setLoading(false);
                navigate('/');
                return;
            }
        }
    } catch (err) {
        setLoading(false);
        setError(err.response?.data?.message || 'Error uploading document');
    }
  };

  const uploadWithRegularUpload = async (file) => {
    setLoading(true);
    setError('');

    const formData = new FormData();
    formData.append('document', file);

    try {
      const response = await axios.post('api/documents', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          const percentCompleted = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          setProgress(percentCompleted);
        }
      });
      
      setLoading(false);
      navigate('/');
    } catch (err) {
      setLoading(false);
      setError(err.response?.data?.message || 'Error uploading document');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      setError('Please select a PDF file to upload');
      return;
    }

    if (useChunks) {
      await uploadWithChunks(file);
    } else {
      await uploadWithRegularUpload(file);
    }
  };

  return (
    <div className="upload-document">
      <h1>Upload Document</h1>
      <p>Upload a PDF document to register it on the blockchain</p>
      
      {error && <div className="error-message">{error}</div>}
      
      <form onSubmit={handleSubmit}>
        <div className="form-group">
          <label htmlFor="document">Select PDF File</label>
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
          {loading ? 'Uploading...' : 'Upload Document'}
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
          <div className="progress-text">{progress}% uploaded</div>
        </div>
      )}
    </div>
  );
};

export default UploadDocument;