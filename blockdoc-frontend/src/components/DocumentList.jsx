// components/DocumentList.jsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';

const DocumentList = () => {
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0
  });

  const fetchDocuments = async (page = 1) => {
    setLoading(true);
    try {
      const response = await axios.get(`/api/documents?page=${page}`);
      
      // Laravel's paginate response has a 'data' property with the actual items
      if (response.data.data) {
        // We're dealing with a paginated response
        setDocuments(response.data.data);
        setPagination({
          currentPage: response.data.current_page,
          lastPage: response.data.last_page,
          perPage: response.data.per_page,
          total: response.data.total
        });
      } else {
        // Not paginated, just use the whole response
        setDocuments(response.data);
      }
      
      setLoading(false);
    } catch (err) {
      console.error('Error fetching documents:', err);
      setError('Error fetching documents: ' + (err.response?.data?.message || err.message));
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDocuments();
  }, []);

  const handlePageChange = (page) => {
    fetchDocuments(page);
  };

  if (loading && documents.length === 0) return <div>Loading documents...</div>;
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
        <>
          <div className="documents-grid">
            {documents.map(doc => (
              <div key={doc.id} className="document-card">
                <div className="document-icon">
                  <i className="far fa-file-pdf"></i>
                </div>
                <div className="document-details">
                  <h3>{doc.original_filename || doc.filename}</h3>
                  <p>Uploaded: {new Date(doc.created_at).toLocaleDateString()}</p>
                  <p className="hash-preview">Hash: {doc.hash.substring(0, 16)}...</p>
                </div>
                <div className="document-actions">
                  <Link to={`/documents/${doc.id}`} className="btn-secondary">View</Link>
                </div>
              </div>
            ))}
          </div>
          
          {/* Pagination controls */}
          {pagination.lastPage > 1 && (
            <div className="pagination">
              <button 
                onClick={() => handlePageChange(pagination.currentPage - 1)}
                disabled={pagination.currentPage === 1}
                className="btn-secondary"
              >
                Previous
              </button>
              
              <span className="page-info">
                Page {pagination.currentPage} of {pagination.lastPage}
              </span>
              
              <button 
                onClick={() => handlePageChange(pagination.currentPage + 1)}
                disabled={pagination.currentPage === pagination.lastPage}
                className="btn-secondary"
              >
                Next
              </button>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default DocumentList;