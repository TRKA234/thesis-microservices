const Message = require('../models/message');

// @desc    Get chat history for a submission
// @route   GET /api/guidance/sessions/:submissionId
// @access  Private
const getChatHistory = async (req, res) => {
    try {
        const messages = await Message.find({ submission_id: req.params.submissionId }).sort({ timestamp: 'asc' });
        res.json({ success: true, data: messages });
    } catch (error) {
        res.status(500).json({ success: false, message: 'Server Error' });
    }
};

// @desc    Create a new message
// @route   POST /api/guidance/sessions
// @access  Private
const createMessage = async (req, res) => {
    try {
        const { submission_id, sender_id, receiver_id, message, attachments } = req.body;
        const newMessage = new Message({
            submission_id,
            sender_id,
            receiver_id,
            message,
            attachments,
        });
        const savedMessage = await newMessage.save();
        res.status(201).json({ success: true, data: savedMessage });
    } catch (error) {
        res.status(500).json({ success: false, message: 'Server Error' });
    }
};

// @desc    Verify a chat message
// @route   PATCH /api/guidance/verify/:chatId
// @access  Private (Lecturer)
const verifyMessage = async (req, res) => {
    try {
        const message = await Message.findById(req.params.chatId);

        if (!message) {
            return res.status(404).json({ success: false, message: 'Message not found' });
        }

        // In a real app, you'd check if the user is a lecturer and is assigned to this student
        message.is_verified_by_lecturer = true;
        await message.save();

        res.json({ success: true, data: message });
    } catch (error) {
        res.status(500).json({ success: false, message: 'Server Error' });
    }
};

module.exports = {
    getChatHistory,
    createMessage,
    verifyMessage,
};
