require('dotenv').config();
const express = require('express');
const connectDB = require('./src/database');
const sessionRoutes = require('./src/routes/session');

const app = express();

// Connect to database
connectDB();

app.use(express.json());

// Routes
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        service: 'guidance-service'
    });
});
app.use('/api/guidance', sessionRoutes);

const PORT = process.env.PORT || 8083;

app.listen(PORT, () => console.log(`Guidance service running on port ${PORT}`));
