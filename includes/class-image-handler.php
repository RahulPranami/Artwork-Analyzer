<?php
/**
 * Image upload validation, preprocessing, and resizing for WP Artwork Analyzer.
 */

defined('ABSPATH') || exit;

class WPA_Art_Analyzer_Image_Handler {

    /**
     * Allowed MIME types mapped to extensions.
     */
    const ALLOWED_MIMES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /**
     * Temporary files created during request to be cleaned up.
     *
     * @var array
     */
    private static $temp_files = [];

    /**
     * Validate and process the uploaded artwork image.
     *
     * @param array $file_entry $_FILES['artwork']
     * @return array|WP_Error Array with mimeType, base64 data, and file path, or WP_Error.
     */
    public static function process_upload($file_entry) {
        if (empty($file_entry) || !isset($file_entry['tmp_name'])) {
            return new WP_Error('INVALID_IMAGE', __('No artwork image was provided.', 'wp-artwork-analyzer'));
        }

        if ($file_entry['error'] !== UPLOAD_ERR_OK) {
            $msg = self::get_upload_error_message($file_entry['error']);
            return new WP_Error('INVALID_IMAGE', $msg);
        }

        $tmp_file = $file_entry['tmp_name'];

        if (!is_uploaded_file($tmp_file) || !file_exists($tmp_file)) {
            return new WP_Error('INVALID_IMAGE', __('Uploaded file could not be verified on the server.', 'wp-artwork-analyzer'));
        }

        // 1. Validate file size
        $max_mb = (float)WPA_Art_Analyzer_Settings::get('max_file_size', 10);
        $max_bytes = $max_mb * 1024 * 1024;
        $file_size = filesize($tmp_file);

        if ($file_size > $max_bytes) {
            return new WP_Error(
                'IMAGE_TOO_LARGE',
                sprintf(
                    /* translators: 1: uploaded file size in megabytes, 2: maximum allowed file size in megabytes */
                    __('The artwork file is too large (%1$s MB). The maximum allowed size is %2$s MB.', 'wp-artwork-analyzer'),
                    round($file_size / 1048576, 2),
                    $max_mb
                )
            );
        }

        // 2. Validate real MIME type on server (do NOT trust $_FILES['type'])
        $real_mime = self::detect_mime_type($tmp_file);

        if (!array_key_exists($real_mime, self::ALLOWED_MIMES)) {
            return new WP_Error(
                'UNSUPPORTED_IMAGE',
                __('Unsupported image format. Please upload a JPG, PNG, or WEBP artwork image.', 'wp-artwork-analyzer')
            );
        }

        // 3. Ensure image is readable and obtain dimensions
        $image_info = function_exists('wp_getimagesize') ? wp_getimagesize($tmp_file) : getimagesize($tmp_file);
        if (!$image_info) {
            return new WP_Error('INVALID_IMAGE', __('Corrupted or invalid image file.', 'wp-artwork-analyzer'));
        }

        $width  = $image_info[0];
        $height = $image_info[1];

        // 4. Resize if dimension exceeds max_dimension
        $max_dimension = (int)WPA_Art_Analyzer_Settings::get('max_dimension', 2048);
        $final_path    = $tmp_file;
        $final_mime    = $real_mime;

        if ($width > $max_dimension || $height > $max_dimension) {
            $resized = self::resize_image($tmp_file, $max_dimension, $real_mime);
            if (!is_wp_error($resized) && file_exists($resized['path'])) {
                $final_path = $resized['path'];
                $final_mime = $resized['mime'];
                self::$temp_files[] = $final_path;
            }
        }

        // 5. Read data and generate base64
        $raw_bytes = file_get_contents($final_path);
        if ($raw_bytes === false || strlen($raw_bytes) === 0) {
            return new WP_Error('INVALID_IMAGE', __('Failed to read processed artwork image data.', 'wp-artwork-analyzer'));
        }

        $base64_data = base64_encode($raw_bytes);
        $image_hash  = hash('sha256', $raw_bytes);

        // 6. Handle optional permanent storage
        $stored_path = '';
        if ((int)WPA_Art_Analyzer_Settings::get('save_images', 0)) {
            $stored_path = self::save_permanent_image($final_path, $file_entry['name'], $final_mime);
        }

        return [
            'mimeType'     => $final_mime,
            'data'         => $base64_data,
            'image_hash'   => $image_hash,
            'stored_path'  => $stored_path,
            'width'        => $width,
            'height'       => $height,
            'original_name'=> sanitize_file_name($file_entry['name']),
        ];
    }

    /**
     * Server-side MIME type detection.
     *
     * @param string $path
     * @return string
     */
    public static function detect_mime_type($path) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (!empty($mime)) {
                return strtolower($mime);
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if (!empty($mime)) {
                return strtolower($mime);
            }
        }

        $info = function_exists('wp_getimagesize') ? wp_getimagesize($path) : getimagesize($path);
        if (!empty($info['mime'])) {
            return strtolower($info['mime']);
        }

        return 'application/octet-stream';
    }

    /**
     * Resize image using WordPress Image Editor.
     *
     * @param string $file_path
     * @param int $max_dimension
     * @param string $mime
     * @return array|WP_Error
     */
    private static function resize_image($file_path, $max_dimension, $mime) {
        $editor = wp_get_image_editor($file_path);
        if (is_wp_error($editor)) {
            return $editor;
        }

        $editor->resize($max_dimension, $max_dimension, false);

        $temp_dir = get_temp_dir();
        $ext = ($mime === 'image/webp') ? 'webp' : (($mime === 'image/png') ? 'png' : 'jpg');
        $dest_filename = 'wpa_' . wp_generate_password(12, false) . '.' . $ext;
        $dest_path = trailingslashit($temp_dir) . $dest_filename;

        $saved = $editor->save($dest_path, $mime);
        if (is_wp_error($saved)) {
            return $saved;
        }

        return [
            'path' => $saved['path'],
            'mime' => $saved['mime-type'],
        ];
    }

    /**
     * Save artwork permanently to WP uploads if enabled.
     *
     * @param string $source_path
     * @param string $original_name
     * @param string $mime
     * @return string Stored file path
     */
    private static function save_permanent_image($source_path, $original_name, $mime) {
        $upload_dir = wp_upload_dir();
        $sub_dir    = trailingslashit($upload_dir['basedir']) . 'artwork-analyses';
        if (!file_exists($sub_dir)) {
            wp_mkdir_p($sub_dir);
        }

        $clean_name = sanitize_file_name($original_name);
        $new_name   = 'art_' . time() . '_' . wp_generate_password(8, false) . '_' . $clean_name;
        $dest_path  = trailingslashit($sub_dir) . $new_name;

        if (copy($source_path, $dest_path)) {
            return $dest_path;
        }
        return '';
    }

    /**
     * Clean up any temporary files.
     */
    public static function cleanup() {
        foreach (self::$temp_files as $file) {
            if (file_exists($file)) {
                wp_delete_file($file);
            }
        }
        self::$temp_files = [];
    }

    /**
     * Friendly upload error message.
     *
     * @param int $error_code
     * @return string
     */
    private static function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the allowed upload limit.', 'wp-artwork-analyzer');
            case UPLOAD_ERR_PARTIAL:
                return __('The file was only partially uploaded. Please try again.', 'wp-artwork-analyzer');
            case UPLOAD_ERR_NO_FILE:
                return __('No artwork image was selected.', 'wp-artwork-analyzer');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Server configuration error: missing temporary folder.', 'wp-artwork-analyzer');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write uploaded file to disk.', 'wp-artwork-analyzer');
            default:
                return __('An unknown upload error occurred.', 'wp-artwork-analyzer');
        }
    }
}
