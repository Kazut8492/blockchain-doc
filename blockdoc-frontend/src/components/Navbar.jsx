// components/Navbar.jsx
import React from 'react';
import { Link, useLocation } from 'react-router-dom';

const Navbar = () => {
  const location = useLocation();
  
  const isActive = (path) => {
    return location.pathname === path ? 'active-nav-link' : '';
  };
  
  return (
    <nav className="bg-white border-b border-gray-200 py-4 shadow-sm">
      <div className="container mx-auto px-4 flex justify-between items-center">
        <Link to="/" className="text-xl font-bold text-blue-600 flex items-center">
          <svg className="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          BlockDoc Verify
        </Link>
        <div className="flex space-x-6">
          <Link 
            to="/" 
            className={`text-gray-600 hover:text-blue-600 font-medium transition-colors ${isActive('/')}`}
          >
            財務書類一覧
          </Link>
          <Link 
            to="/create-entry" 
            className={`text-gray-600 hover:text-blue-600 font-medium transition-colors ${isActive('/create-entry')}`}
          >
            新規作成
          </Link>
          <Link 
            to="/verify" 
            className={`text-gray-600 hover:text-blue-600 font-medium transition-colors ${isActive('/verify')}`}
          >
            検証
          </Link>
        </div>
      </div>
    </nav>
  );
};

export default Navbar;