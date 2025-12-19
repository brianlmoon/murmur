<?php

declare(strict_types=1);

namespace Murmur\Service;

/**
 * Service for handling image uploads.
 *
 * Validates and stores uploaded images.
 */
class ImageService {

    /**
     * Allowed MIME types for uploads.
     */
    protected const ALLOWED_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Maximum file size in bytes (5MB).
     */
    protected const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * Base directory for uploads.
     */
    protected string $upload_dir;

    /**
     * Creates a new ImageService instance.
     *
     * @param string $upload_dir Base directory for uploads.
     */
    public function __construct(string $upload_dir) {
        $this->upload_dir = rtrim($upload_dir, '/');
    }

    /**
     * Processes an uploaded image file.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     *        The $_FILES array entry for the upload.
     * @param string $subdirectory Subdirectory within uploads (e.g., 'posts', 'avatars').
     *
     * @return array{success: bool, path?: string, error?: string}
     */
    public function upload(array $file, string $subdirectory = 'posts'): array {
        $result = ['success' => false];

        $validation_error = $this->validateUpload($file);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } else {
            $target_dir = $this->upload_dir . '/' . $subdirectory;

            // Create directory if it doesn't exist
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            // Generate unique filename
            $extension = $this->getExtension($file['type']);
            $filename = $this->generateFilename($extension);
            $target_path = $target_dir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Return the relative path for storage in database
                $result['success'] = true;
                $result['path'] = $subdirectory . '/' . $filename;
            } else {
                $result['error'] = 'Failed to save uploaded file.';
            }
        }

        return $result;
    }

    /**
     * Validates an uploaded file.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     *        The $_FILES array entry.
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateUpload(array $file): ?string {
        $error = null;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = $this->getUploadErrorMessage($file['error']);
        } elseif ($file['size'] > self::MAX_SIZE) {
            $error = 'File is too large. Maximum size is 5MB.';
        } elseif (!in_array($file['type'], self::ALLOWED_TYPES, true)) {
            $error = 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP.';
        } elseif (!$this->isValidImage($file['tmp_name'])) {
            $error = 'File does not appear to be a valid image.';
        }

        return $error;
    }

    /**
     * Verifies that a file is actually an image.
     *
     * @param string $path Path to the temporary file.
     *
     * @return bool True if the file is a valid image.
     */
    protected function isValidImage(string $path): bool {
        $result = false;

        $info = @getimagesize($path);

        if ($info !== false) {
            $result = true;
        }

        return $result;
    }

    /**
     * Gets the file extension for a MIME type.
     *
     * @param string $mime_type The MIME type.
     *
     * @return string The file extension.
     */
    protected function getExtension(string $mime_type): string {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        return $extensions[$mime_type] ?? 'jpg';
    }

    /**
     * Generates a unique filename.
     *
     * @param string $extension The file extension.
     *
     * @return string The generated filename.
     */
    protected function generateFilename(string $extension): string {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    /**
     * Gets a human-readable error message for upload errors.
     *
     * @param int $error_code The PHP upload error code.
     *
     * @return string The error message.
     */
    protected function getUploadErrorMessage(int $error_code): string {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server configuration error: cannot write to disk.',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by server extension.',
        ];

        return $messages[$error_code] ?? 'Unknown upload error.';
    }

    /**
     * Deletes an uploaded image.
     *
     * @param string $path The relative path to the image.
     *
     * @return bool True if deleted successfully.
     */
    public function delete(string $path): bool {
        $result = false;

        $full_path = $this->upload_dir . '/' . $path;

        if (file_exists($full_path)) {
            $result = unlink($full_path);
        }

        return $result;
    }

    /**
     * Gets the full filesystem path for an image.
     *
     * @param string $path The relative path.
     *
     * @return string The full path.
     */
    public function getFullPath(string $path): string {
        return $this->upload_dir . '/' . $path;
    }

    /**
     * Checks if an uploaded file was provided.
     *
     * @param array{error: int}|null $file The $_FILES array entry.
     *
     * @return bool True if a file was uploaded.
     */
    public function hasUpload(?array $file): bool {
        $result = false;

        if ($file !== null && isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = true;
        }

        return $result;
    }
}
