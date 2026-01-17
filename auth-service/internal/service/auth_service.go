package service

import (
	"auth-service/internal/model"
	"auth-service/internal/repository"
	"errors"
	"time"

	"github.com/golang-jwt/jwt/v5"
	"golang.org/x/crypto/bcrypt"
)

type AuthService interface {
	Register(req *model.RegisterRequest) (*model.AuthResponse, error)
	Login(req *model.LoginRequest) (*model.AuthResponse, error)
	ValidateToken(tokenString string) (*Claims, error)
	GetUserByID(id uint) (*model.User, error)
	GetAllUsers() ([]model.UserInfo, error)
}

type authService struct {
	userRepo  repository.UserRepository
	jwtSecret string
}

type Claims struct {
	UserID         uint   `json:"user_id"`
	IdentityNumber string `json:"identity_number"`
	Role           string `json:"role"`
	jwt.RegisteredClaims
}

func NewAuthService(userRepo repository.UserRepository, jwtSecret string) AuthService {
	return &authService{
		userRepo:  userRepo,
		jwtSecret: jwtSecret,
	}
}

func (s *authService) Register(req *model.RegisterRequest) (*model.AuthResponse, error) {
	// Check if user already exists
	existingUser, _ := s.userRepo.FindByIdentityNumber(req.IdentityNumber)
	if existingUser != nil {
		return nil, errors.New("identity number already registered")
	}

	// Hash password
	hashedPassword, err := bcrypt.GenerateFromPassword([]byte(req.Password), bcrypt.DefaultCost)
	if err != nil {
		return nil, err
	}

	// Create user
	user := &model.User{
		IdentityNumber: req.IdentityNumber,
		FullName:       req.FullName,
		Password:       string(hashedPassword),
		Role:           req.Role,
	}

	if err := s.userRepo.Create(user); err != nil {
		return nil, err
	}

	// Generate token
	token, err := s.generateToken(user)
	if err != nil {
		return nil, err
	}

	return &model.AuthResponse{
		Token: token,
		User: &model.UserInfo{
			ID:             user.ID,
			IdentityNumber: user.IdentityNumber,
			FullName:       user.FullName,
			Role:           user.Role,
		},
	}, nil
}

func (s *authService) Login(req *model.LoginRequest) (*model.AuthResponse, error) {
	// Find user
	user, err := s.userRepo.FindByIdentityNumber(req.IdentityNumber)
	if err != nil {
		return nil, errors.New("invalid credentials")
	}

	// Verify password
	if err := bcrypt.CompareHashAndPassword([]byte(user.Password), []byte(req.Password)); err != nil {
		return nil, errors.New("invalid credentials")
	}

	// Generate token
	token, err := s.generateToken(user)
	if err != nil {
		return nil, err
	}

	return &model.AuthResponse{
		Token: token,
		User: &model.UserInfo{
			ID:             user.ID,
			IdentityNumber: user.IdentityNumber,
			FullName:       user.FullName,
			Role:           user.Role,
		},
	}, nil
}

func (s *authService) generateToken(user *model.User) (string, error) {
	claims := &Claims{
		UserID:         user.ID,
		IdentityNumber: user.IdentityNumber,
		Role:           user.Role,
		RegisteredClaims: jwt.RegisteredClaims{
			ExpiresAt: jwt.NewNumericDate(time.Now().Add(24 * time.Hour)),
			IssuedAt:  jwt.NewNumericDate(time.Now()),
		},
	}

	token := jwt.NewWithClaims(jwt.SigningMethodHS256, claims)
	return token.SignedString([]byte(s.jwtSecret))
}

func (s *authService) ValidateToken(tokenString string) (*Claims, error) {
	token, err := jwt.ParseWithClaims(tokenString, &Claims{}, func(token *jwt.Token) (interface{}, error) {
		return []byte(s.jwtSecret), nil
	})

	if err != nil {
		return nil, err
	}

	if claims, ok := token.Claims.(*Claims); ok && token.Valid {
		return claims, nil
	}

	return nil, errors.New("invalid token")
}

func (s *authService) GetUserByID(id uint) (*model.User, error) {
	return s.userRepo.FindByID(id)
}

func (s *authService) GetAllUsers() ([]model.UserInfo, error) {
    users, err := s.userRepo.FindAll()
    if err != nil {
        return nil, err
    }

    var userInfos []model.UserInfo
    for _, user := range users {
        userInfos = append(userInfos, model.UserInfo{
            ID:             user.ID,
            IdentityNumber: user.IdentityNumber,
            FullName:       user.FullName,
            Role:           user.Role,
        })
    }
    return userInfos, nil
}