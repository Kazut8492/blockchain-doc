// components/FiscalEntryList.jsx
import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import axios from 'axios';

const FiscalEntryList = () => {
  const [entries, setEntries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    perPage: 10,
    total: 0
  });

  const fetchEntries = async (page = 1) => {
    setLoading(true);
    try {
      const response = await axios.get(`/api/fiscal-entries?page=${page}`);
      
      if (response.data.data) {
        // We're dealing with a paginated response
        setEntries(response.data.data);
        setPagination({
          currentPage: response.data.current_page,
          lastPage: response.data.last_page,
          perPage: response.data.per_page,
          total: response.data.total
        });
      } else {
        // Not paginated, just use the whole response
        setEntries(response.data);
      }
      
      setLoading(false);
    } catch (err) {
      console.error('Error fetching entries:', err);
      setError('Error fetching entries: ' + (err.response?.data?.message || err.message));
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchEntries();
  }, []);

  const handlePageChange = (page) => {
    fetchEntries(page);
  };

  const handleDeleteEntry = async (entryId) => {
    if (!window.confirm('Are you sure you want to delete this entry?')) {
      return;
    }
    
    try {
      await axios.delete(`/api/fiscal-entries/${entryId}`);
      // Refresh the entries list
      fetchEntries(pagination.currentPage);
    } catch (err) {
      console.error('Error deleting entry:', err);
      setError('Error deleting entry: ' + (err.response?.data?.message || err.message));
    }
  };

  const getStatusLabel = (status) => {
    switch (status) {
      case 'active':
        return <span className="inline-block px-2 py-1 bg-green-100 text-green-800 rounded">Active</span>;
      case 'deleted':
        return <span className="inline-block px-2 py-1 bg-red-100 text-red-800 rounded">Deleted</span>;
      default:
        return <span className="inline-block px-2 py-1 bg-gray-100 text-gray-800 rounded">{status}</span>;
    }
  };

  const getBlockchainStatusLabel = (documents) => {
    if (!documents || documents.length === 0) {
      return <span className="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded">No Document</span>;
    }
    
    const doc = documents[0]; // Assuming one document per entry for now
    
    switch (doc.blockchain_status) {
      case 'confirmed':
        return <span className="inline-block px-2 py-1 bg-green-100 text-green-800 rounded">登録済み</span>;
      case 'pending':
        return (
          <button className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            ブロックチェーンへ登録
          </button>
        );
      case 'failed':
        return (
          <button className="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600 transition">
            再登録する
          </button>
        );
      default:
        return <span className="inline-block px-2 py-1 bg-gray-100 text-gray-800 rounded">{doc.blockchain_status}</span>;
    }
  };

  if (loading && entries.length === 0) return <div>Loading entries...</div>;
  if (error) return <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 my-4">{error}</div>;

  return (
    <div className="document-list">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">財務書類一覧</h1>
        <Link 
          to="/create-entry" 
          className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
        >
          新規作成
        </Link>
      </div>
      
      {entries.length === 0 ? (
        <div className="bg-white rounded-lg border p-8 text-center">
          <p className="text-gray-600 mb-4">財務書類が登録されていません。</p>
          <Link to="/create-entry" className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition">
            最初の書類を作成する
          </Link>
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full bg-white border border-gray-200">
            <thead>
              <tr className="bg-gray-100">
                <th className="py-3 px-4 border-b border-r text-left">決算年度</th>
                <th className="py-3 px-4 border-b border-r text-left">決算期間</th>
                <th className="py-3 px-4 border-b border-r text-left">保存書類</th>
                <th className="py-3 px-4 border-b border-r text-center">ブロックチェーン登録</th>
                <th className="py-3 px-4 border-b border-r text-left">作成者</th>
                <th className="py-3 px-4 border-b border-r text-left">最終更新者</th>
                <th className="py-3 px-4 border-b border-r text-left">更新日</th>
                <th className="py-3 px-4 border-b text-center">ステータス</th>
              </tr>
            </thead>
            <tbody>
              {entries.map(entry => (
                <tr key={entry.id} className="hover:bg-gray-50">
                  <td className="py-3 px-4 border-b border-r">{entry.fiscal_year}</td>
                  <td className="py-3 px-4 border-b border-r">{entry.fiscal_period}</td>
                  <td className="py-3 px-4 border-b border-r">
                    {entry.documents && entry.documents.length > 0 ? (
                      <Link 
                        to={`/documents/${entry.documents[0].id}`} 
                        className="text-blue-600 hover:underline flex items-center"
                      >
                        {entry.document_type}
                        <svg className="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      </Link>
                    ) : (
                      <Link 
                        to={`/upload?entryId=${entry.id}`} 
                        className="text-blue-600 hover:underline flex items-center"
                      >
                        {entry.document_type}
                        <svg className="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4" />
                        </svg>
                      </Link>
                    )}
                  </td>
                  <td className="py-3 px-4 border-b border-r text-center">
                    {getBlockchainStatusLabel(entry.documents)}
                  </td>
                  <td className="py-3 px-4 border-b border-r">{entry.creator}</td>
                  <td className="py-3 px-4 border-b border-r">{entry.last_modifier}</td>
                  <td className="py-3 px-4 border-b border-r">
                    {new Date(entry.updated_at).toLocaleDateString()}
                    <span className="ml-2 cursor-pointer">
                      <svg className="w-5 h-5 inline-block text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                      </svg>
                    </span>
                  </td>
                  <td className="py-3 px-4 border-b text-center">
                    {entry.status === 'active' ? (
                      <button
                        onClick={() => handleDeleteEntry(entry.id)}
                        className="text-red-600 hover:text-red-800 p-1 rounded-md hover:bg-red-100 transition-colors"
                      >
                        削除する
                      </button>
                    ) : (
                      <span className="text-gray-400">削除済み</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      
      {/* Pagination controls */}
      {pagination.lastPage > 1 && (
        <div className="mt-6 flex justify-center items-center space-x-4">
          <button 
            onClick={() => handlePageChange(pagination.currentPage - 1)}
            disabled={pagination.currentPage === 1}
            className={`px-4 py-2 rounded ${
              pagination.currentPage === 1 
              ? 'bg-gray-300 cursor-not-allowed' 
              : 'bg-blue-500 text-white hover:bg-blue-600'
            }`}
          >
            前へ
          </button>
          
          <span className="text-gray-600">
            {pagination.currentPage} / {pagination.lastPage} ページ
          </span>
          
          <button 
            onClick={() => handlePageChange(pagination.currentPage + 1)}
            disabled={pagination.currentPage === pagination.lastPage}
            className={`px-4 py-2 rounded ${
              pagination.currentPage === pagination.lastPage 
              ? 'bg-gray-300 cursor-not-allowed' 
              : 'bg-blue-500 text-white hover:bg-blue-600'
            }`}
          >
            次へ
          </button>
        </div>
      )}
    </div>
  );
};

export default FiscalEntryList;