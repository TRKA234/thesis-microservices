package handler

import (
	"auth-service/internal/model"
	"auth-service/internal/service"
	"net/http"

	"github.com/gin-gonic/gin"
)

type AuthHandler struct {
	authService service.AuthService
}

func NewAuthHandler(authService service.AuthService) *AuthHandler {
	return &AuthHandler{authService: authService}
}

func (h *AuthHandler) Register(c *gin.Context) {
	var req model.RegisterRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"message": "Invalid request body",
			"error":   err.Error(),
		})
		return
	}

	response, err := h.authService.Register(&req)
	if err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"message": err.Error(),
		})
		return
	}

	c.JSON(http.StatusCreated, gin.H{
		"success": true,
		"message": "User registered successfully",
		"data":    response,
	})
}

func (h *AuthHandler) Login(c *gin.Context) {
	var req model.LoginRequest
	if err := c.ShouldBindJSON(&req); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{
			"success": false,
			"message": "Invalid request body",
			"error":   err.Error(),
		})
		return
	}

	response, err := h.authService.Login(&req)
	if err != nil {
		c.JSON(http.StatusUnauthorized, gin.H{
			"success": false,
			"message": err.Error(),
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"message": "Login successful",
		"data":    response,
	})
}

func (h *AuthHandler) GetProfile(c *gin.Context) {
	userID, exists := c.Get("user_id")
	if !exists {
		c.JSON(http.StatusUnauthorized, gin.H{
			"success": false,
			"message": "Unauthorized",
		})
		return
	}

	user, err := h.authService.GetUserByID(userID.(uint))
	if err != nil {
		c.JSON(http.StatusNotFound, gin.H{
			"success": false,
			"message": "User not found",
		})
		return
	}

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"data": gin.H{
			"id":              user.ID,
			"identity_number": user.IdentityNumber,
			"full_name":       user.FullName,
			"role":            user.Role,
		},
	})
}

func (h *AuthHandler) VerifyToken(c *gin.Context) {
	userID, _ := c.Get("user_id")
	identityNumber, _ := c.Get("identity_number")
	role, _ := c.Get("role")

	c.JSON(http.StatusOK, gin.H{
		"success": true,
		"message": "Token is valid",
		"data": gin.H{
			"user_id":         userID,
			"identity_number": identityNumber,
			"role":            role,
		},
	})
}

func (h *AuthHandler) GetAllUsers(c *gin.Context) {
    users, err := h.authService.GetAllUsers()
    if err != nil {
        c.JSON(500, gin.H{"error": "Failed to fetch users"})
        return
    }

    c.JSON(200, gin.H{
        "success": true,
        "data":    users,
    })
}