<?php

namespace App\Command;

use Carbon\Carbon;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Image as AssetImage;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Product;
use Pimcore\Model\Element\Service as ElementService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:products:import', description: 'Import products from a JSON URL')]
class ImportProductsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'URL to JSON with products');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $url = (string)$input->getArgument('url');

        try {
            $json = $this->fetchJson($url);
        } catch (\Throwable $e) {
            $io->error('Failed to fetch JSON: ' . $e->getMessage());
            return Command::FAILURE;
        }

        if (!isset($json['products']) || !is_array($json['products'])) {
            $io->error('Invalid JSON structure. Expected key "products" with an array value.');
            return Command::FAILURE;
        }

        $count = 0;

        foreach ($json['products'] as $idx => $row) {

            if (!is_array($row)) {
                $io->warning("Skip item #$idx: not an object");
                continue;
            }

            $name = isset($row['name']) ? (string)$row['name'] : null;
            $gtin = isset($row['gtin']) ? (int)$row['gtin'] : null;
            $image = $row['image'] ?? null;
            $date  = $row['date'] ?? null;

            if ($gtin === null || $gtin <= 0) {
                $io->warning("Skip item #$idx: missing gtin");
                continue;
            }

            if ($name === null) {
                $io->warning("Skip item #$idx: missing name");
                continue;
            }

            $product = $this->getOrCreateProductByGtin($gtin, $io);

            if (!$product) {
                $io->error("Failed to init product with GTIN $gtin");
                continue;
            }

            // Name to uppercase
            $product->setName(mb_strtoupper($name));

            // Date
            if ($date) {
                try {
                    $carbon = Carbon::parse($date);
                    $product->setDate($carbon);
                } catch (\Throwable $e) {
                    $io->warning("GTIN $gtin: invalid date '$date' - " . $e->getMessage());
                }
            }

            // Image
            if ($image) {
                try {
                    $asset = $this->resolveOrCreateImageAsset($image, $gtin, $io);

                    if ($asset instanceof AssetImage) {
                        $product->setImage($asset);
                    } else {
                        $io->warning("GTIN $gtin: image not found/created for '$image'");
                    }

                } catch (\Throwable $e) {
                    $io->warning("GTIN $gtin: image handling failed for '$image' - " . $e->getMessage());
                }
            }

            // Field is numeric; cast to int if possible
            if (ctype_digit($gtin)) {
                $product->setGtin($gtin);
            } else {
                // if not purely digits, try to strip non-digits
                $digits = preg_replace('/\D+/', '', $gtin);

                if ($digits !== '') {
                    $product->setGtin((int)$digits);
                }
            }

            // Save product
            try {
                $product->save();
                $count++;
                $io->success("Upserted product GTIN $gtin (ID: {$product->getId()})");
            } catch (\Throwable $e) {
                $io->error("Failed to save product GTIN $gtin: " . $e->getMessage());
            }
        }

        $io->success("Imported/updated $count product(s).");
        return Command::SUCCESS;
    }

    /**
     * @return array<string,mixed>
     */
    private function fetchJson(string $url): array
    {
        if (preg_match('#^https?://#i', $url)) {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
                'https' => [
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ]);
            $data = file_get_contents($url, false, $ctx);
        } else {
            // allow reading from local file path
            $data = file_get_contents($url);
        }

        if ($data === false) {
            throw new \RuntimeException('Unable to read from ' . $url);
        }

        $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    private function getOrCreateProductByGtin(int $gtin, SymfonyStyle $io): ?Product
    {
        $existing = Product::getByGtin($gtin, 1);

        if ($existing instanceof Product) {
            return $existing;
        }

        // Create new one
        $folderPath = '/products';
        /** @var DataObject\Folder $folder */
        $folder = DataObject\Service::createFolderByPath($folderPath);

        $product = new Product();
        $product->setParentId($folder->getId());
        $product->setKey($gtin);
        $product->setPublished(true);

        return $product;
    }

    private function resolveOrCreateImageAsset(string $image, string $gtin, SymfonyStyle $io): ?AssetImage
    {
        if (preg_match('#^https?://#i', $image)) {
            // external URL: download and create under /product-images
            $folderPath = '/product-images';
            /** @var Asset\Folder $folder */
            $folder = Asset\Service::createFolderByPath($folderPath);

            $urlPath = parse_url($image, PHP_URL_PATH) ?: '';
            $filename = basename($urlPath) ?: ('product-' . $gtin . '.jpg');

            // If asset with same name already exists under folder, reuse
            $existing = Asset::getByPath($folderPath . '/' . $filename);

            if ($existing instanceof AssetImage) {
                return $existing;
            }

            $binary = @file_get_contents($image);
            if ($binary === false) {
                throw new \RuntimeException('Failed to download image: ' . $image);
            }

            $asset = new AssetImage();
            $asset->setParent($folder);
            $asset->setFilename($filename);
            $asset->setData($binary);
            $asset->setUserOwner(1);
            $asset->setUserModification(1);
            $asset->save();

            return $asset;
        }

        // Assume it's an internal Pimcore asset path
        if ($image[0] !== '/') {
            $image = '/' . $image;
        }

        $asset = Asset::getByPath($image);

        if ($asset instanceof AssetImage) {
            return $asset;
        }

        return null;
    }
}
