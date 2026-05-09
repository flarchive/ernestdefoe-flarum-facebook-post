<?php

namespace Ernestdefoe\FacebookPost\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UploadDefaultImageController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected FilesystemFactory $filesystem,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $files = $request->getUploadedFiles();

        if (empty($files['image'])) {
            return new JsonResponse(['error' => 'No file uploaded.'], 422);
        }

        $file = $files['image'];

        if ($file->getError() !== UPLOAD_ERR_OK) {
            return new JsonResponse(['error' => 'Upload error.'], 422);
        }

        $mimeType = $file->getClientMediaType();
        if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            return new JsonResponse(['error' => 'Invalid file type.'], 422);
        }

        $ext      = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = 'facebook-post-default.' . $ext;

        $disk = $this->filesystem->disk('flarum-assets');
        $disk->put($filename, $file->getStream()->getContents());

        $url = $disk->url($filename);

        $this->settings->set('ernestdefoe-facebook-post.default_image_url', $url);

        return new JsonResponse(['url' => $url]);
    }
}
