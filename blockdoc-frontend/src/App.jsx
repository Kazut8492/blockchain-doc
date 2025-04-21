// App.jsx - Updated Main application component
import React from 'react';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import Navbar from './components/Navbar';
import UploadDocument from './components/UploadDocument';
import DocumentList from './components/DocumentList';
import ViewDocument from './components/ViewDocument';
import VerifyDocument from './components/VerifyDocument';
import FiscalEntryList from './components/FiscalEntryList';
import CreateFiscalEntry from './components/CreateFiscalEntry';
import './App.css';

function App() {
  return (
    <Router>
      <div className="app">
        <Navbar />
        <main className="container">
          <Routes>
            <Route path="/" element={<FiscalEntryList />} />
            <Route path="/documents" element={<DocumentList />} />
            <Route path="/upload" element={<UploadDocument />} />
            <Route path="/documents/:id" element={<ViewDocument />} />
            <Route path="/verify" element={<VerifyDocument />} />
            <Route path="/create-entry" element={<CreateFiscalEntry />} />
          </Routes>
        </main>
      </div>
    </Router>
  );
}

export default App;