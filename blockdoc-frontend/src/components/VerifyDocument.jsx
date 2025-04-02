// components/VerifyDocument.jsx
import React, { useState } from 'react';
import axios from 'axios';

const VerifyDocument = () => {
  const [file, setFile] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [verificationResult, setVerificationResult] = useState(null);

  const handleFileChange = (e) => {
    const selectedFile = e.target.files[0];
    if (selectedFile && selectedFile.type === 'application/pdf') {
      setFile(selectedFile);
      setError('');
      setVerificationResult(null);
    } else {
      setFile(null);
      setError('Please select a valid PDF file');
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!file) {
      setError('Please select a PDF file to verify');
      return;
    }

    setLoading(true);
    setError('');
    setVerificationResult(null);

    const formData = new FormData();
    formData.append('document', file);

    try {
      const response = await axios.post('api/verify', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      });
      
      setVerificationResult(response.data);
      setLoading(false);
    } catch (err) {
      setLoading(false);
      setError(err.response?.data?.message || 'Error verifying document');
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
        </div>
        
        <button 
          type="submit" 
          className="btn-primary" 
          disabled={loading || !file}
        >
          {loading ? 'Verifying...' : 'Verify Document'}
        </button>
      </form>
      
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
            </div>
          ) : (
            <div className="verification-details">
              <p>This document has not been registered on our blockchain.</p>
              <p>Document Hash: {verificationResult.hash}</p>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default VerifyDocument;