<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers\Admin;

use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Media\InvalidImageException;
use BlazingSun\Api\Media\MediaStore;
use BlazingSun\Api\Page\Picture;

/** POST /api/admin/media — multipart upload: file, purpose (image | flag). */
final readonly class MediaController
{
    /** @param \Closure(string): bool $isUploadedFile is_uploaded_file() in production */
    public function __construct(
        private MediaStore $store,
        private \Closure $isUploadedFile,
    ) {
    }

    public function upload(Request $request): JsonResponse
    {
        $file = $request->files['file'] ?? null;
        if (!is_array($file) || !is_string($file['tmp_name'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $tooBig = is_array($file) && in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
            return new JsonResponse(422, ['error' => $tooBig ? 'Images must be 8 MB or smaller' : 'Choose an image to upload', 'fields' => []]);
        }
        if (!($this->isUploadedFile)($file['tmp_name'])) {
            return JsonResponse::error(400, 'Invalid upload');
        }
        $purpose = $request->form['purpose'] ?? 'image';

        try {
            $media = $this->store->store($file['tmp_name'], is_string($purpose) ? $purpose : '');
        } catch (InvalidImageException $error) {
            return new JsonResponse(422, ['error' => $error->getMessage(), 'fields' => ['file' => $error->getMessage()]]);
        }

        return new JsonResponse(201, [
            'id' => $media['id'],
            'kind' => $media['kind'],
            'url' => Picture::previewUrl($media),
            'width' => $media['width'],
            'height' => $media['height'],
        ]);
    }
}
