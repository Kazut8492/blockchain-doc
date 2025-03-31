// components/ViewDocument.jsx
import React, { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';
import { Document, Page, pdfjs } from 'react-pdf';

pdfjs.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js";

const ViewDocument = () => {
  const { id } = useParams();
  const [document, setDocument] = useState(null);
  const [numPages, setNumPages] = useState(null);
  const [pageNumber, setPageNumber] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchDocument = async () => {
      try {
        const response = await axios.get(`api/documents/${id}`);
        setDocument(response.data);
        setLoading(false);
      } catch (err) {
        setError('Error fetching document');
        setLoading(false);
      }
    };

    fetchDocument();
  }, [id]);

  const onDocumentLoadSuccess = ({ numPages }) => {
    setNumPages(numPages);
  };

  const goToPrevPage = () => {
    if (pageNumber > 1) {
      setPageNumber(pageNumber - 1);
    }
  };

  const goToNextPage = () => {
    if (pageNumber < numPages) {
      setPageNumber(pageNumber + 1);
    }
  };

  if (loading) return <div>Loading document...</div>;
  if (error) return <div className="error-message">{error}</div>;
  if (!document) return <div className="error-message">Document not found</div>;

  return (
    <div className="view-document">
      <h1>{document.filename}</h1>
      
      <div className="document-details">
        <p>Uploaded: {new Date(document.created_at).toLocaleDateString()}</p>
        <p className="hash">SHA512 Hash: {document.hash}</p>
        <div className="blockchain-status">
          {document.blockchain_status === 'confirmed' ? (
            <div className="status-confirmed">
              <p>Verified on Blockchain</p>
              <p>Transaction Hash: {document.transaction_hash}</p>
              <a 
                href={`https://etherscan.io/tx/${document.transaction_hash}`} 
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
        <Document
          file={`/api/documents/${id}/download`}
          onLoadSuccess={onDocumentLoadSuccess}
        >
          <Page pageNumber={pageNumber} />
        </Document>
        
        <div className="pdf-controls">
          <button 
            onClick={goToPrevPage} 
            disabled={pageNumber <= 1}
            className="btn-secondary"
          >
            Previous
          </button>
          <p>
            Page {pageNumber} of {numPages}
          </p>
          <button 
            onClick={goToNextPage} 
            disabled={pageNumber >= numPages}
            className="btn-secondary"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  );
};

export default ViewDocument;