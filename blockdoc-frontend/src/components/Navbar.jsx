// components/Navbar.jsx
import React from 'react';
import { Link } from 'react-router-dom';

const Navbar = () => {
  return (
    <nav className="navbar">
      <div className="container">
        <Link to="/" className="navbar-brand">
          BlockDoc Verify
        </Link>
        <div className="navbar-menu">
          <Link to="/" className="navbar-item">My Documents</Link>
          <Link to="/upload" className="navbar-item">Upload</Link>
          <Link to="/verify" className="navbar-item">Verify</Link>
        </div>
      </div>
    </nav>
  );
};

export default Navbar;