<?php

declare(strict_types=1);

namespace Murmur\Service;

use Murmur\Storage\StorageInterface;

/**
 * Service for handling media uploads (images and videos).
 *
 * Validates uploaded media files and delegates storage operations to a
 * StorageInterface implementation. Supports local filesystem, S3,
 * and other storage backends via the storage abstraction layer.
 *
 * Usage:
 * ```php
 * $storage = StorageFactory::create($config);
 * $media_service = new MediaService($storage, 100 * 1024 * 1024);
 *
 * $result = $media_service->upload($_FILES['media'], 'posts');
 * if ($result['success']) {
 *     $url = $media_service->getUrl($result['path']);
 *     $type = $result['media_type']; // 'image' or 'video'
 * }
 * ```
 */
class MediaService {

    /**
     * Allowed MIME types for image uploads.
     */
    protected const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Allowed MIME types for video uploads.
     */
    protected const ALLOWED_VIDEO_TYPES = [
        'video/mp4',
        'video/webm',
    ];

    /**
     * Maximum image file size in bytes (5MB).
     */
    protected const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    /**
     * Storage backend for file operations.
     */
    protected StorageInterface $storage;

    /**
     * Maximum video file size in bytes.
     */
    protected int $max_video_size;

    /**
     * Creates a new MediaService instance.
     *
     * @param StorageInterface $storage        Storage backend for file operations.
     * @param int              $max_video_size Maximum video file size in bytes (default 100MB).
     */
    public function __construct(StorageInterface $storage, int $max_video_size = 100 * 1024 * 1024) {
        $this->storage = $storage;
        $this->max_video_size = $max_video_size;
    }

    /**
     * Processes an uploaded media file (image or video).
     *
     * Validates the uploaded file and stores it using the configured
     * storage backend. Returns a result array indicating success or failure.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     *        The $_FILES array entry for the upload.
     * @param string $subdirectory Subdirectory within uploads (e.g., 'posts', 'avatars').
     *
     * @return array{success: bool, path?: string, media_type?: string, error?: string}
     *         On success: ['success' => true, 'path' => 'posts/abc123.mp4', 'media_type' => 'video']
     *         On failure: ['success' => false, 'error' => 'Error message']
     */
    public function upload(array $file, string $subdirectory = 'posts'): array {
        $result = ['success' => false];

        $media_type = $this->getMediaType($file['type']);
        $validation_error = $this->validateUpload($file, $media_type);

        if ($validation_error !== null) {
            $result['error'] = $validation_error;
        } else {
            $extension = $this->getExtension($file['type']);
            $filename = $this->generateFilename($extension);
            $path = $subdirectory . '/' . $filename;

            if ($this->storage->writeFromPath($path, $file['tmp_name'])) {
                $result['success'] = true;
                $result['path'] = $path;
                $result['media_type'] = $media_type;
            } else {
                $result['error'] = 'Failed to save uploaded file.';
            }
        }

        return $result;
    }

    /**
     * Determines the media type based on MIME type.
     *
     * @param string $mime_type The file's MIME type.
     *
     * @return string 'image', 'video', or 'unknown'.
     */
    public function getMediaType(string $mime_type): string {
        $result = 'unknown';

        if (in_array($mime_type, self::ALLOWED_IMAGE_TYPES, true)) {
            $result = 'image';
        } elseif (in_array($mime_type, self::ALLOWED_VIDEO_TYPES, true)) {
            $result = 'video';
        }

        return $result;
    }

    /**
     * Validates an uploaded file.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     *        The $_FILES array entry.
     * @param string $media_type The detected media type ('image', 'video', or 'unknown').
     *
     * @return string|null Error message or null if valid.
     */
    protected function validateUpload(array $file, string $media_type): ?string {
        $error = null;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = $this->getUploadErrorMessage($file['error']);
        } elseif ($media_type === 'unknown') {
            $error = 'Invalid file type. Allowed: JPEG, PNG, GIF, WebP, MP4, WebM.';
        } elseif ($media_type === 'image') {
            if ($file['size'] > self::MAX_IMAGE_SIZE) {
                $error = 'Image is too large. Maximum size is 5MB.';
            } elseif (!$this->isValidImage($file['tmp_name'], $file['type'])) {
                $error = 'File does not appear to be a valid image or MIME type mismatch.';
            }
        } elseif ($media_type === 'video') {
            if ($file['size'] > $this->max_video_size) {
                $max_mb = (int) ($this->max_video_size / (1024 * 1024));
                $error = 'Video is too large. Maximum size is ' . $max_mb . 'MB.';
            } elseif (!$this->isValidVideo($file['tmp_name'], $file['type'])) {
                $error = 'File does not appear to be a valid video or MIME type mismatch.';
            }
        }

        return $error;
    }

    /**
     * Verifies that a file is actually an image with matching MIME type.
     *
     * Uses `getimagesize()` to detect the actual MIME type from file content,
     * then validates it against the allowed types and the claimed MIME type.
     * This prevents polyglot attacks where a file passes basic image validation
     * but has a mismatched or spoofed MIME type.
     *
     * @param string $path         Path to the temporary file.
     * @param string $claimed_mime The MIME type claimed by the upload (from $_FILES['type']).
     *
     * @return bool True if the file is a valid image and MIME types match.
     */
    protected function isValidImage(string $path, string $claimed_mime): bool {
        $result = false;

        $info = @getimagesize($path);

        if ($info !== false && isset($info['mime'])) {
            $detected_mime = $info['mime'];

            // Verify detected MIME is in our allowed list and matches claimed type
            if (in_array($detected_mime, self::ALLOWED_IMAGE_TYPES, true) &&
                $detected_mime === $claimed_mime)
            {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Verifies that a file is actually a video with matching MIME type.
     *
     * Uses `finfo_file()` to detect the actual MIME type from file content,
     * then validates it against the allowed types and the claimed MIME type.
     *
     * @param string $path         Path to the temporary file.
     * @param string $claimed_mime The MIME type claimed by the upload (from $_FILES['type']).
     *
     * @return bool True if the file is a valid video and MIME types match.
     */
    protected function isValidVideo(string $path, string $claimed_mime): bool {
        $result = false;

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected_mime = $finfo->file($path);

        if ($detected_mime !== false) {
            // Verify detected MIME is in our allowed list and matches claimed type
            if (in_array($detected_mime, self::ALLOWED_VIDEO_TYPES, true) &&
                $detected_mime === $claimed_mime)
            {
                $result = true;
            }
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
            'image/jpeg'  => 'jpg',
            'image/png'   => 'png',
            'image/gif'   => 'gif',
            'image/webp'  => 'webp',
            'video/mp4'   => 'mp4',
            'video/webm'  => 'webm',
        ];

        return $extensions[$mime_type] ?? 'bin';
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
            UPLOAD_ERR_INI_SIZE   => 'File exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds the form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error: missing temp directory.',
            UPLOAD_ERR_CANT_WRITE => 'Server configuration error: cannot write to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];

        return $messages[$error_code] ?? 'Unknown upload error.';
    }

    /**
     * Deletes an uploaded media file.
     *
     * This operation is idempotent - deleting a non-existent file
     * returns true rather than failing.
     *
     * @param string $path The relative path to the file (e.g., 'posts/abc123.mp4').
     *
     * @return bool True if deleted successfully or file didn't exist.
     */
    public function delete(string $path): bool {
        return $this->storage->delete($path);
    }

    /**
     * Gets the public URL for a media file.
     *
     * The URL format varies by storage backend:
     * - Local: `/uploads/posts/video.mp4`
     * - S3: `https://bucket.s3.region.amazonaws.com/posts/video.mp4`
     *
     * @param string $path The relative path to the file.
     *
     * @return string Public URL to access the file.
     */
    public function getUrl(string $path): string {
        return $this->storage->getUrl($path);
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

    /**
     * Checks if any uploaded files were provided in a multi-file upload.
     *
     * Handles the $_FILES array structure for inputs with name="media[]".
     * The structure is: ['name' => [...], 'type' => [...], 'tmp_name' => [...], ...]
     *
     * @param array|null $files The $_FILES array entry for multi-file input.
     *
     * @return bool True if at least one file was uploaded.
     */
    public function hasUploads(?array $files): bool {
        $result = false;

        if ($files !== null && isset($files['error']) && is_array($files['error'])) {
            foreach ($files['error'] as $error) {
                if ($error !== UPLOAD_ERR_NO_FILE) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Processes multiple uploaded media files.
     *
     * Validates all files first (atomic - all or nothing). If any file fails
     * validation, the entire upload is rejected and no files are stored.
     *
     * @param array  $files        The $_FILES array entry for multi-file input.
     * @param string $subdirectory Subdirectory within uploads (e.g., 'posts', 'avatars').
     * @param int    $max_files    Maximum number of files allowed.
     *
     * @return array{success: bool, paths?: array<array{path: string, media_type: string}>, error?: string}
     *         On success: ['success' => true, 'paths' => [['path' => 'posts/abc.mp4', 'media_type' => 'video'], ...]]
     *         On failure: ['success' => false, 'error' => 'Error message']
     */
    public function uploadMultiple(array $files, string $subdirectory = 'posts', int $max_files = 10): array {
        $result = ['success' => false];

        // Normalize the multi-file array structure
        $normalized_files = $this->normalizeFilesArray($files);

        // Filter out empty uploads
        $normalized_files = array_filter($normalized_files, function ($file) {
            return $file['error'] !== UPLOAD_ERR_NO_FILE;
        });

        if (empty($normalized_files)) {
            $result['success'] = true;
            $result['paths'] = [];
        } elseif (count($normalized_files) > $max_files) {
            $result['error'] = 'Too many files. Maximum allowed is ' . $max_files . '.';
        } else {
            // Validate all files first (atomic validation)
            $validation_errors = [];
            foreach ($normalized_files as $index => $file) {
                $media_type = $this->getMediaType($file['type']);
                $error = $this->validateUpload($file, $media_type);
                if ($error !== null) {
                    $validation_errors[] = 'File ' . ($index + 1) . ': ' . $error;
                }
            }

            if (!empty($validation_errors)) {
                $result['error'] = implode(' ', $validation_errors);
            } else {
                // All files valid, proceed with upload
                $paths = [];
                $upload_error = null;

                foreach ($normalized_files as $file) {
                    $upload_result = $this->upload($file, $subdirectory);
                    if (!$upload_result['success']) {
                        $upload_error = $upload_result['error'];
                        break;
                    }
                    $paths[] = [
                        'path'       => $upload_result['path'],
                        'media_type' => $upload_result['media_type'],
                    ];
                }

                if ($upload_error !== null) {
                    // Rollback: delete any files that were uploaded
                    foreach ($paths as $path_info) {
                        $this->delete($path_info['path']);
                    }
                    $result['error'] = $upload_error;
                } else {
                    $result['success'] = true;
                    $result['paths'] = $paths;
                }
            }
        }

        return $result;
    }

    /**
     * Normalizes the multi-file $_FILES array structure.
     *
     * Converts from: ['name' => ['a.jpg', 'b.mp4'], 'type' => [...], ...]
     * To: [['name' => 'a.jpg', 'type' => ...], ['name' => 'b.mp4', ...]]
     *
     * @param array $files The $_FILES array entry for multi-file input.
     *
     * @return array<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    protected function normalizeFilesArray(array $files): array {
        $result = [];

        if (!isset($files['name']) || !is_array($files['name'])) {
            // Already normalized or single file
            if (isset($files['name'])) {
                $result[] = $files;
            }
        } else {
            foreach ($files['name'] as $index => $name) {
                $result[] = [
                    'name'     => $name,
                    'type'     => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $files['size'][$index] ?? 0,
                ];
            }
        }

        return $result;
    }

    /**
     * Enriches post items with media URLs for templates.
     *
     * Takes an array of post items (as returned by PostService methods) and
     * adds `image_urls`, `video_urls`, and `avatar_url` keys based on each post's
     * attachments and author's avatar_path. This centralizes URL generation
     * for posts displayed in feeds, profiles, and other listing pages.
     *
     * Usage:
     * ```php
     * $posts = $post_service->getFeed(20, 0, $user_id);
     * $posts = $media_service->enrichPostsWithUrls($posts);
     * // Now each $posts[n] has 'image_urls', 'video_urls', and 'avatar_url' keys
     * ```
     *
     * @param array<int, array{post: \Murmur\Entity\Post, author: \Murmur\Entity\User, attachments?: array}> $posts
     *        Array of post items from PostService methods.
     *
     * @return array<int, array{post: \Murmur\Entity\Post, author: \Murmur\Entity\User, image_urls: array<string>, video_urls: array<string>, avatar_url: ?string}>
     *         Enriched post items with URL keys added.
     */
    public function enrichPostsWithUrls(array $posts): array {
        foreach ($posts as $key => $post_item) {
            $image_urls = [];
            $video_urls = [];

            if (isset($post_item['attachments']) && is_array($post_item['attachments'])) {
                foreach ($post_item['attachments'] as $attachment) {
                    $url = $this->getUrl($attachment->file_path);
                    if ($attachment->media_type === 'video') {
                        $video_urls[] = $url;
                    } else {
                        $image_urls[] = $url;
                    }
                }
            }

            $posts[$key]['image_urls'] = $image_urls;
            $posts[$key]['video_urls'] = $video_urls;

            $posts[$key]['avatar_url'] = $post_item['author']->avatar_path !== null
                ? $this->getUrl($post_item['author']->avatar_path)
                : null;
        }

        return $posts;
    }
}
