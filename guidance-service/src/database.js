const mongoose = require('mongoose');

const connectDB = async () => {
    try {
        // Tambahkan opsi retry jika mongo belum siap saat docker compose up
        await mongoose.connect(process.env.MONGODB_URI); 
        console.log('MongoDB connected successfully');
    } catch (error) {
        console.error('MongoDB connection error:', error.message);
        // Jangan langsung exit di development agar docker tidak restart loop terlalu cepat
        setTimeout(connectDB, 5000); 
    }
};

module.exports = connectDB;
