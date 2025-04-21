// components/CreateFiscalEntry.jsx
import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import axios from 'axios';

const CreateFiscalEntry = () => {
  const [formData, setFormData] = useState({
    fiscal_year: '',
    fiscal_period: '本決算',
    document_type: '損益計算書',
    creator: '',
  });
  
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();
  
  // List of document types
  const documentTypes = [
    '損益計算書',
    '貸借対照表',
    'キャッシュフロー計算書',
    '株主資本等変動計算書',
  ];
  
  // List of fiscal periods
  const fiscalPeriods = [
    '本決算',
    '中間決算',
    '第1四半期',
    '第2四半期',
    '第3四半期',
    '第4四半期'
  ];
  
  // Generate fiscal years (last 10 years)
  const generateFiscalYears = () => {
    const currentYear = new Date().getFullYear();
    const years = [];
    
    for (let i = 0; i < 10; i++) {
      const year = currentYear - i;
      years.push(`${year}/${year - 1999}`); // Japanese fiscal year format: 2023/24
    }
    
    return years;
  };
  
  const fiscalYears = generateFiscalYears();

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    
    try {
      const response = await axios.post('/api/fiscal-entries', formData);
      
      // Redirect to upload document page with the entry ID
      navigate(`/upload?entryId=${response.data.entry.id}`);
    } catch (err) {
      console.error('Error creating entry:', err);
      setError(err.response?.data?.message || 'Failed to create fiscal entry');
      setLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto p-4">
      <h1 className="text-2xl font-bold text-gray-800 mb-6">新規財務書類の作成</h1>
      
      {error && (
        <div className="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
          {error}
        </div>
      )}
      
      <form onSubmit={handleSubmit} className="bg-white rounded-lg border p-6 shadow-sm">
        <div className="mb-4">
          <label className="block text-gray-700 font-medium mb-2" htmlFor="fiscal_year">
            決算年度
          </label>
          <select
            id="fiscal_year"
            name="fiscal_year"
            value={formData.fiscal_year}
            onChange={handleChange}
            required
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">選択してください</option>
            {fiscalYears.map(year => (
              <option key={year} value={year}>{year}期</option>
            ))}
          </select>
        </div>
        
        <div className="mb-4">
          <label className="block text-gray-700 font-medium mb-2" htmlFor="fiscal_period">
            決算期間
          </label>
          <select
            id="fiscal_period"
            name="fiscal_period"
            value={formData.fiscal_period}
            onChange={handleChange}
            required
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {fiscalPeriods.map(period => (
              <option key={period} value={period}>{period}</option>
            ))}
          </select>
        </div>
        
        <div className="mb-4">
          <label className="block text-gray-700 font-medium mb-2" htmlFor="document_type">
            書類種別
          </label>
          <select
            id="document_type"
            name="document_type"
            value={formData.document_type}
            onChange={handleChange}
            required
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            {documentTypes.map(type => (
              <option key={type} value={type}>{type}</option>
            ))}
          </select>
        </div>
        
        <div className="mb-6">
          <label className="block text-gray-700 font-medium mb-2" htmlFor="creator">
            作成者
          </label>
          <input
            type="text"
            id="creator"
            name="creator"
            value={formData.creator}
            onChange={handleChange}
            required
            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="作成者名を入力"
          />
        </div>
        
        <div className="flex justify-between">
          <button
            type="button"
            onClick={() => navigate('/')}
            className="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-500"
          >
            キャンセル
          </button>
          
          <button
            type="submit"
            disabled={loading}
            className={`px-4 py-2 bg-blue-600 text-white rounded-md ${
              loading ? 'opacity-70 cursor-not-allowed' : 'hover:bg-blue-700'
            } focus:outline-none focus:ring-2 focus:ring-blue-500`}
          >
            {loading ? '処理中...' : '次へ'}
          </button>
        </div>
      </form>
    </div>
  );
};

export default CreateFiscalEntry;