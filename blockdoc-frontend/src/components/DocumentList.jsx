// components/DocumentList.jsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';

const DocumentList = () => {
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    const fetchDocuments = async () => {
      try {
        const response = await axios.get('/api/documents');
        setDocuments(response.data);
        setLoading(false);
      } catch (err) {
        setError('Error fetching documents');
        setLoading(false);
      }
    };

    fetchDocuments();
  }, []);

  if (loading) return <div>Loading documents...</div>;
  if (error) return <div className="error-message">{error}</div>;

  return (
    <div className="document-list">
      <h1>My Documents</h1>
      
      {documents.length === 0 ? (
        <div className="empty-state">
          <p>You haven't uploaded any documents yet.</p>
          <Link to="/upload" className="btn-primary">Upload Your First Document</Link>
        </div>
      ) : (
        <div className="documents-grid">
          {documents.map(doc => (
            <div key={doc.id} className="document-card">
              <div className="document-icon">
                <i className="far fa-file-pdf"></i>
              </div>
              <div className="document-details">
                <h3>{doc.filename}</h3>
                <p>Uploaded: {new Date(doc.created_at).toLocaleDateString()}</p>
                <p className="hash-preview">Hash: {doc.hash.substring(0, 16)}...</p>
                <div className="blockchain-status">
                  {doc.blockchain_status === 'confirmed' ? (
                    <span className="status-confirmed">Verified on Blockchain</span>
                  ) : (
                    <span className="status-pending">Pending Confirmation</span>
                  )}
                </div>
              </div>
              <div className="document-actions">
                <Link to={`/documents/${doc.id}`} className="btn-secondary">View</Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default DocumentList;