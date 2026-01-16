package model

import (
	"time"

	"gorm.io/gorm"
)

type User struct {
	ID             uint           `gorm:"primaryKey" json:"id"`
	IdentityNumber string         `gorm:"uniqueIndex;not null" json:"identity_number"`
	FullName       string         `gorm:"not null" json:"full_name"`
	Password       string         `gorm:"not null" json:"-"`
	Role           string         `gorm:"not null;check:role IN ('mahasiswa','dosen','kaprodi')" json:"role"`
	CreatedAt      time.Time      `json:"created_at"`
	UpdatedAt      time.Time      `json:"updated_at"`
	DeletedAt      gorm.DeletedAt `gorm:"index" json:"-"`
}

type RegisterRequest struct {
	IdentityNumber string `json:"identity_number" binding:"required"`
	FullName       string `json:"full_name" binding:"required"`
	Password       string `json:"password" binding:"required,min=6"`
	Role           string `json:"role" binding:"required,oneof=mahasiswa dosen kaprodi"`
}

type LoginRequest struct {
	IdentityNumber string `json:"identity_number" binding:"required"`
	Password       string `json:"password" binding:"required"`
}

type AuthResponse struct {
	Token string    `json:"token"`
	User  *UserInfo `json:"user"`
}

type UserInfo struct {
	ID             uint   `json:"id"`
	IdentityNumber string `json:"identity_number"`
	FullName       string `json:"full_name"`
	Role           string `json:"role"`
}