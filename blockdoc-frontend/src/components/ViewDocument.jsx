// components/ViewDocument.jsx
import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';
import { Worker, Viewer } from '@react-pdf-viewer/core';
import '@react-pdf-viewer/core/lib/styles/index.css';

const ViewDocument = () => {
  const { id } = useParams();
  const [documentData, setDocumentData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchDocument = async () => {
      try {
        const response = await axios.get(`api/documents/${id}`);
        setDocumentData(response.data);
        setLoading(false);
      } catch (err) {
        setError('Error fetching document');
        setLoading(false);
      }
    };

    fetchDocument();
  }, [id]);

  if (loading) return <div>Loading document...</div>;
  if (error) return <div className="error-message">{error}</div>;
  if (!documentData) return <div className="error-message">Document not found</div>;

  return (
    <div className="view-document">
      <h1>{documentData.filename}</h1>
      
      <div className="document-details">
        <p>Uploaded: {new Date(documentData.created_at).toLocaleDateString()}</p>
        <p className="hash">SHA512 Hash: {documentData.hash}</p>
        <div className="blockchain-status">
          {documentData.blockchain_status === 'confirmed' ? (
            <div className="status-confirmed">
              <p>Verified on Blockchain</p>
              <p>Transaction Hash: {documentData.transaction_hash}</p>
              <a 
                href={`https://sepolia.etherscan.io/tx/${documentData.transaction_hash}`} 
                target="_blank" 
                rel="noopener noreferrer"
                className="etherscan-link"
              >
                View on Etherscan
              </a>
            </div>
          ) : (
            <div className="status-pending">
              <p>Pending Blockchain Confirmation</p>
            </div>
          )}
        </div>
      </div>
      
      <div className="pdf-viewer">
        <Worker workerUrl="https://unpkg.com/pdfjs-dist@3.4.120/build/pdf.worker.min.js">
          {/* Due to Cors issue, the fileUrl must be a full URL. */}
          <Viewer fileUrl={`http://localhost:8000/api/documents/${id}/download`} />
        </Worker>
      </div>
    </div>
  );
};

export default ViewDocument;