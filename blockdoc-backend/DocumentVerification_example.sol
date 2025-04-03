// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract DocumentVerification {
    address public owner;
    
    // Mapping from document hash to its registration timestamp
    mapping(string => uint256) private documents;
    
    event DocumentRegistered(string indexed documentHash, uint256 timestamp);
    
    constructor() {
        owner = msg.sender;
    }
    
    modifier onlyOwner() {
        require(msg.sender == owner, "Only the owner can call this function");
        _;
    }
    
    function registerDocument(string memory documentHash) public returns (bool) {
        // Check if document already registered
        require(documents[documentHash] == 0, "Document already registered");
        
        // Register document with current timestamp
        documents[documentHash] = block.timestamp;
        
        // Emit event
        emit DocumentRegistered(documentHash, block.timestamp);
        
        return true;
    }
    
    function verifyDocument(string memory documentHash) public view returns (bool, uint256) {
        uint256 timestamp = documents[documentHash];
        
        // If timestamp is 0, document is not registered
        return (timestamp > 0, timestamp);
    }
    
    function getDocumentTimestamp(string memory documentHash) public view returns (uint256) {
        return documents[documentHash];
    }
    
    // Allow owner to transfer ownership
    function transferOwnership(address newOwner) public onlyOwner {
        require(newOwner != address(0), "New owner cannot be the zero address");
        owner = newOwner;
    }
}