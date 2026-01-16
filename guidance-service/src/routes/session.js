const express = require('express');
const router = express.Router();
const {
    getChatHistory,
    createMessage,
    verifyMessage,
} = require('../controllers/sessionController');

// In a real app, you would have an auth middleware to protect these routes
// const auth = require('../middleware/auth');

router.route('/sessions/:submissionId').get(getChatHistory);
router.route('/sessions').post(createMessage);
router.route('/verify/:chatId').patch(verifyMessage);

module.exports = router;
