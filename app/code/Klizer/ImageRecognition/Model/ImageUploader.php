<?php
/**
 * Copyright © Klizer. All rights reserved.
 */
declare(strict_types=1);

namespace Klizer\ImageRecognition\Model;

use Klizer\ImageRecognition\Helper\Data as ImageRecognitionHelper;
use Klizer\ImageRecognition\Model\Api\ClipSearchClient;
use Klizer\ImageRecognition\Model\Search\ResultStorage;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;

class ImageUploader
{
    private const UPLOAD_DIR = 'imagerecognition/tmp';

    private WriteInterface $mediaDirectory;

    public function __construct(
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly ImageRecognitionHelper $helper,
        private readonly ClipSearchClient $clipSearchClient,
        private readonly ResultStorage $resultStorage,
        private readonly RequestInterface $request
    ) {
        $this->mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * @return array{productIds: int[], matchCount: int, file: string}
     * @throws LocalizedException
     */
    public function processUpload(string $fileId = 'image'): array
    {
        if (!$this->helper->isEnabled()) {
            throw new LocalizedException(__('Image search is currently disabled.'));
        }

        $file = $this->request->getFiles($fileId);
        if ($file instanceof \Magento\Framework\DataObject) {
            $file = $file->toArray();
        }
        if (!is_array($file) || empty($file['tmp_name'])) {
            throw new LocalizedException(__('Please select an image to upload.'));
        }

        $this->validateFile($file);

        $this->mediaDirectory->create(self::UPLOAD_DIR);

        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions($this->helper->getAllowedExtensions());
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);

        $result = $uploader->save($this->mediaDirectory->getAbsolutePath(self::UPLOAD_DIR));
        if (!$result || empty($result['file'])) {
            throw new LocalizedException(__('Unable to upload the image. Please try again.'));
        }

        $relativePath = self::UPLOAD_DIR . '/' . ltrim((string) $result['file'], '/');
        $absolutePath = $this->mediaDirectory->getAbsolutePath($relativePath);
        $originalName = (string) ($file['name'] ?? $result['name'] ?? 'image');

        $matches = $this->clipSearchClient->searchByImage($absolutePath, $originalName);
        $this->resultStorage->saveMatches($matches);
        $this->resultStorage->saveQueryImage($relativePath);

        $productIds = [];
        foreach ($matches as $match) {
            $id = (int) ($match['productId'] ?? 0);
            if ($id > 0) {
                $productIds[] = $id;
            }
        }

        return [
            'productIds' => array_values(array_unique($productIds)),
            'matchCount' => count($productIds),
            'file' => $relativePath,
        ];
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     * @throws LocalizedException
     */
    private function validateFile(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new LocalizedException(__('Image upload failed. Please try again.'));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $this->helper->getMaxFileSizeBytes()) {
            throw new LocalizedException(__(
                'Image must be smaller than %1 MB.',
                $this->helper->getMaxFileSizeMb()
            ));
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, $this->helper->getAllowedExtensions(), true)) {
            throw new LocalizedException(__(
                'Allowed image types: %1.',
                implode(', ', $this->helper->getAllowedExtensions())
            ));
        }

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new LocalizedException(__('Invalid upload request.'));
        }
    }
}
