const mongoose = require('mongoose');

const messageSchema = new mongoose.Schema({
    submission_id: {
        type: Number,
        required: true,
    },
    sender_id: {
        type: String,
        required: true,
    },
    receiver_id: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    timestamp: {
        type: Date,
        default: Date.now,
    },
    is_verified_by_lecturer: {
        type: Boolean,
        default: false,
    },
    attachments: [
        {
            file_name: String,
            file_url: String,
        },
    ],
});

module.exports = mongoose.model('Message', messageSchema);
