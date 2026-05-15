# Offloading (IOT) Module — API Documentation

All Offloading APIs require **JWT Bearer Token** authentication.

**Base URL:** `{{BASE_URL}}/api`

**Auth Header (required on every request):**
```
Authorization: Bearer <jwt_token>
```

---

## Table of Contents

1. [Post Feedback (Submit Offloading)](#1-post-feedback)
2. [Get Feedback List](#2-get-feedback-list)
3. [Send Chat Message](#3-send-chat-message)
4. [Get Inbox / Chat List](#4-get-inbox--chat-list)
5. [Get Chat Messages](#5-get-chat-messages)
6. [Get Theme List](#6-get-theme-list)

---

## 1. Post Feedback

Submit a new offloading entry from the user.

**Endpoint:** `POST /api/iot-post-feedback`

### Request Body

| Field      | Type   | Required | Description                                     |
|------------|--------|----------|-------------------------------------------------|
| `message`  | string | ✅ Yes   | The offloading/feedback message text            |
| `userId`   | int    | ✅ Yes   | ID of the user submitting the feedback          |
| `orgId`    | int    | ✅ Yes   | ID of the organisation                          |
| `image`    | string | ❌ No    | Base64-encoded image (with or without data URI prefix) |
| `SWOT`     | string | ❌ No    | SWOT category label (max 255 chars)             |
| `themeId`  | string | ❌ No    | Theme ID to associate with this feedback        |

### Example Request

```json
{
  "message": "I am feeling overwhelmed with the current workload",
  "userId": 5,
  "orgId": 2,
  "SWOT": "Weakness",
  "themeId": "3",
  "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUg..."
}
```

### Success Response `200`

```json
{
  "code": 200,
  "status": true,
  "service_name": "iot-post-feedback",
  "message": "Record added successfully",
  "data": []
}
```

### Error Response `400`

```json
{
  "code": 400,
  "status": false,
  "service_name": "iot-post-feedback",
  "message": "The message field is required.",
  "data": []
}
```

### Notes
- On success, the user's `EIScore` is incremented by **100 points**
- A notification email is sent to `offloads@tribe365.co`
- Image is saved to `public/uploads/iot_files/`

---

## 2. Get Feedback List

Get all feedbacks submitted by a user, along with their chat messages.

**Endpoint:** `POST /api/iot-get-feedback-detail`

### Request Body

| Field    | Type | Required | Description                          |
|----------|------|----------|--------------------------------------|
| `userId` | int  | ✅ Yes   | ID of the user                       |
| `page`   | int  | ❌ No    | Page number for pagination (default: 1) |

### Example Request

```json
{
  "userId": 5,
  "page": 1
}
```

### Success Response `200`

```json
{
  "code": 200,
  "status": true,
  "service_name": "iot-get-feedback-detail",
  "message": "",
  "data": [
    {
      "id": 12,
      "message": "I am feeling overwhelmed with the current workload",
      "image": "http://example.com/uploads/iot_files/iotFeedback_1234567890.png",
      "imageFileType": "image",
      "createdAt": "2026-05-10 09:30:00",
      "status": "Active",
      "messages": [
        {
          "id": 45,
          "sendTo": 5,
          "sendFrom": 1,
          "name": "Admin User",
          "message": "Thank you for sharing. We will look into this.",
          "created_at": "2026-05-10 10:15:00",
          "userImageUrl": "http://example.com/storage/profile-photos/admin.jpg",
          "msgImageUrl": "",
          "msgFileType": null,
          "userType": "Admin"
        }
      ]
    }
  ],
  "totalPageCount": 3,
  "currentPage": 1
}
```

### Error Response `400`

```json
{
  "code": 400,
  "status": false,
  "service_name": "iot-get-feedback-detail",
  "message": "The selected user id is invalid.",
  "data": []
}
```

### Notes
- Returns **10 records per page**
- Only feedbacks with `status = Active` are returned
- `imageFileType` can be `"image"`, `"video"`, or `null`
- `userType` is `"Admin"` for admin users, `"User"` for regular users

---

## 3. Send Chat Message

Send a chat message (text, image, or video) in reply to a feedback.

**Endpoint:** `POST /api/iot-send-msg`

### Request Body

| Field        | Type   | Required | Description                                              |
|--------------|--------|----------|----------------------------------------------------------|
| `sendFrom`   | int    | ✅ Yes   | User ID of the sender                                    |
| `sendTo`     | int    | ✅ Yes   | User ID of the recipient                                 |
| `feedbackId` | int    | ✅ Yes   | ID of the feedback this message belongs to               |
| `postType`   | string | ✅ Yes   | Type of message: `msg`, `img`, or `video`                |
| `message`    | string | ❌ No    | Text message content (required if `postType = msg`)      |
| `image`      | string | ❌ No    | Base64-encoded image (used when `postType = img`)        |

### `postType` Values

| Value   | Description                                                    |
|---------|----------------------------------------------------------------|
| `msg`   | Plain text message. Use `message` field for content.          |
| `img`   | Image message. Use `image` field with Base64-encoded data.    |
| `video` | Video message. Use `message` field with Base64-encoded video. |

### Example Request — Text Message

```json
{
  "sendFrom": 5,
  "sendTo": 1,
  "feedbackId": 12,
  "postType": "msg",
  "message": "Is there any update on my concern?"
}
```

### Example Request — Image Message

```json
{
  "sendFrom": 5,
  "sendTo": 1,
  "feedbackId": 12,
  "postType": "img",
  "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUg..."
}
```

### Success Response `200`

```json
{
  "code": 200,
  "status": true,
  "service_name": "iot-send-msg",
  "message": "",
  "data": {
    "messages": [
      {
        "id": 45,
        "sendTo": 1,
        "sendFrom": 5,
        "name": "John Doe",
        "message": "Is there any update on my concern?",
        "created_at": "2026-05-13 11:00:00",
        "userImageUrl": "http://example.com/storage/profile-photos/john.jpg",
        "msgImageUrl": "",
        "msgFileType": null,
        "userType": "User"
      }
    ]
  }
}
```

### Error Response `400`

```json
{
  "code": 400,
  "status": false,
  "service_name": "iot-send-msg",
  "message": "The post type field is required.",
  "data": []
}
```

### Notes
- Returns the **full updated message list** for the feedback after sending
- A notification email is sent to `team@tribe365.co` on every message
- Files are saved to `public/uploads/iot_files/`
- Supported video formats: `mp4`, `mov`, `avi`, `wmv`, `flv`, `webm`
- Supported image formats: `jpg`, `jpeg`, `png`, `gif`, `webp`

---

## 4. Get Inbox / Chat List

Get the list of feedbacks that have at least one chat message (inbox view).

**Endpoint:** `POST /api/iot-inbox-list`

### Request Body

| Field    | Type | Required | Description        |
|----------|------|----------|--------------------|
| `userId` | int  | ✅ Yes   | ID of the user     |

### Example Request

```json
{
  "userId": 5
}
```

### Success Response `200`

```json
{
  "code": 200,
  "status": true,
  "service_name": "iot-inbox-list",
  "message": "",
  "data": {
    "inbox": [
      {
        "id": 12,
        "feedback_msg": "I am feeling overwhelmed with the current workload",
        "message": "Thank you for sharing. We will look into this.",
        "date": "2026-05-10 10:15:00",
        "image": false
      },
      {
        "id": 9,
        "feedback_msg": "Team communication needs improvement",
        "message": "",
        "date": "2026-05-08 14:30:00",
        "image": true
      }
    ]
  }
}
```

### Error Response `400`

```json
{
  "code": 400,
  "status": false,
  "service_name": "iot-inbox-list",
  "message": "The selected user id is invalid.",
  "data": []
}
```

### Notes
- Only feedbacks with at least **one active message** are returned
- `message` contains the **last chat message** text
- `image` is `true` if the last message contains an image/video attachment
- Results are ordered by **newest feedback first**

---

## 5. Get Chat Messages

Get all messages for a specific feedback thread.

**Endpoint:** `POST /api/iot-get-msg`

### Request Body

| Field        | Type | Required | Description                    |
|--------------|------|----------|--------------------------------|
| `feedbackId` | int  | ✅ Yes   | ID of the feedback thread      |

### Example Request

```json
{
  "feedbackId": 12
}
```

### Success Response `200`

```json
{
  "code": 200,
  "status": true,
  "service_name": "iot-get-msg",
  "message": "",
  "data": {
    "feedback": {
      "initialMessage": "I am feeling overwhelmed with the current workload",
      "initialMsgDate": "2026-05-10 09:30:00",
      "msgImageUrl": "http://example.com/uploads/iot_files/iotFeedback_1234567890.png",
      "msgFileType": "image"
    },
    "messages": [
      {
        "id": 44,
        "sendTo": 1,
        "sendFrom": 5,
        "name": "John Doe",
        "message": "Is there any update on my concern?",
        "created_at": "2026-05-10 10:00:00",
        "userImageUrl": "http://example.com/storage/profile-photos/john.jpg",
        "msgImageUrl": "",
        "msgFileType": null,
        "userType": "User"
      },
      {
        "id": 45,
        "sendTo": 5,
        "sendFrom": 1,
        "name": "Admin User",
        "message": "Thank you for sharing. We will look into this.",
        "created_at": "2026-05-10 10:15:00",
        "userImageUrl": "",
        "msgImageUrl": "",
        "msgFileType": null,
        "userType": "Admin"
      }
    ]
  }
}
```

### Error Response `400`

```json
{
  "code": 400,
  "status": false,
  "service_name": "iot-get-msg",
  "message": "The selected feedback id is invalid.",
  "data": []
}
```

### Notes
- `feedback` object contains the **original offloading message** details
- `messages` are ordered **oldest to newest** (ascending by ID)
- `msgFileType` can be `"image"`, `"video"`, or `null`
- `userType` is `"Admin"` for admin role, `"User"` for others

---

## 6. Get Theme List

Get the list of available themes for an organisation (used when submitting a new offloading).

**Endpoint:** `POST /api/iot-get-theme-list`

### Request Body

| Field   | Type | Required | Description              |
|---------|------|----------|--------------------------|
| `orgId` | int  | ✅ Yes   | ID of the organisation   |

### Example Request

```json
{
  "orgId": 2
}
```

### Success Response `200`

```json
{
  "code": 200,
  "status": true,
  "service_name": "iot-get-theme-list",
  "message": "",
  "data": {
    "themeList": [
      {
        "id": 1,
        "title": "Work-Life Balance"
      },
      {
        "id": 2,
        "title": "Team Conflict"
      },
      {
        "id": 3,
        "title": "Workload Management"
      }
    ]
  }
}
```

### Error Response `400`

```json
{
  "code": 400,
  "status": false,
  "service_name": "iot-get-theme-list",
  "message": "The selected org id is invalid.",
  "data": []
}
```

### Notes
- Only themes with `status = Active` are returned
- Results ordered by **newest theme first**
- Use the returned `id` as `themeId` when calling [Post Feedback](#1-post-feedback)

---

## Common Response Fields

| Field          | Type    | Description                                    |
|----------------|---------|------------------------------------------------|
| `code`         | int     | HTTP status code (`200` success, `400` error)  |
| `status`       | boolean | `true` on success, `false` on failure          |
| `service_name` | string  | Internal service identifier for this endpoint  |
| `message`      | string  | Human-readable message (empty string on success) |
| `data`         | object/array | Response payload                          |

## File Type Reference

| `msgFileType` value | Description          |
|---------------------|----------------------|
| `"image"`           | jpg, jpeg, png, gif, webp |
| `"video"`           | mp4, mov, avi, wmv, flv, webm |
| `null`              | No file attached     |

## Error Codes

| HTTP Code | Meaning                                    |
|-----------|--------------------------------------------|
| `200`     | Success                                    |
| `400`     | Validation error or business logic failure |
| `401`     | Unauthorized — invalid or missing token    |
