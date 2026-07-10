-- Campus Lost & Found Management System
-- Database Schema Definition

-- Create Database if not exists
CREATE DATABASE IF NOT EXISTS campus_lost_found CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_lost_found;

-- 1. Users Table
-- Stores student profiles, authentication hashes, and verification info
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NULL AFTER email,
    profile_photo VARCHAR(255) NULL AFTER phone,
    password_hash VARCHAR(255) NOT NULL,
    verification_code VARCHAR(255),
    is_verified TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Lost Items Table
-- Stores lost item reports linked to the user who posted them
CREATE TABLE IF NOT EXISTS lost_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    last_seen_location VARCHAR(255) NOT NULL,
    date_lost DATE NOT NULL,
    photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
