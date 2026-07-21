<?php
/**
 * One-shot importer: create up to 100 Industrial simple products with images.
 *
 * Usage:
 *   php app/code/Klizer/ImageRecognition/bin/import-industrial-products.php
 *   php app/code/Klizer/ImageRecognition/bin/import-industrial-products.php --limit=50
 *   php app/code/Klizer/ImageRecognition/bin/import-industrial-products.php --skip-download
 */
declare(strict_types=1);

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File as IoFile;
use Magento\Store\Model\StoreManagerInterface;

require dirname(__DIR__, 5) . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();

/** @var State $state */
$state = $om->get(State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (\Exception) {
}

$options = getopt('', ['limit::', 'skip-download']);
$limit = isset($options['limit']) ? max(1, (int) $options['limit']) : 100;
$skipDownload = array_key_exists('skip-download', $options);

$products = [
    'Ball Bearing 6205', 'Tapered Roller Bearing', 'Needle Bearing Set', 'Thrust Bearing Plate',
    'Industrial Gearbox Helical', 'Worm Gear Reducer', 'Planetary Gear Set', 'Sprocket Wheel 40B',
    'Timing Belt HTD 8M', 'V-Belt B55', 'Coupling Flexible Jaw', 'Shaft Collar Clamp',
    'Hydraulic Cylinder 50mm', 'Pneumatic Actuator', 'Solenoid Valve 24V', 'Pressure Relief Valve',
    'Ball Valve Stainless 1in', 'Gate Valve Cast Iron', 'Check Valve Spring', 'Butterfly Valve DN100',
    'AC Induction Motor 1HP', 'Servo Motor 400W', 'Stepper Motor NEMA23', 'Gear Motor 12V',
    'Variable Frequency Drive', 'Soft Starter 15kW', 'Contactor 3-Pole', 'Thermal Overload Relay',
    'Proximity Sensor Inductive', 'Photoelectric Sensor', 'Limit Switch Roller', 'Encoder Rotary Absolute',
    'Temperature Transmitter PT100', 'Pressure Transducer 10bar', 'Flow Meter Turbine', 'Level Sensor Ultrasonic',
    'PLC Compact Module', 'HMI Touch Panel 7in', 'Industrial Switch Gigabit', 'I/O Expansion Card',
    'Welding Electrode Pack', 'MIG Wire Spool', 'Cutting Disc 125mm', 'Grinding Wheel Abrasive',
    'Carbide End Mill 10mm', 'Twist Drill Bit Set', 'Tap and Die Kit', 'Reamer Straight Flute',
    'Hydraulic Hose Assembly', 'Pneumatic Push Fitting', 'Quick Coupler Set', 'Pipe Flange ANSI',
    'Industrial Fastener Kit', 'Hex Bolt M12 Pack', 'Lock Nut Nylon Insert', 'Spring Washer Assortment',
    'Linear Guide Rail', 'Ball Screw Assembly', 'Pillow Block Bearing', 'Cam Follower Stud',
    'Industrial Chain Roller', 'Conveyor Belt Modular', 'Idler Roller Steel', 'Tensioner Unit',
    'Air Compressor Filter', 'Oil Filter Spin-On', 'Hydraulic Filter Cartridge', 'Dust Collector Bag',
    'Safety Interlock Switch', 'Emergency Stop Button', 'Light Curtain Sensor', 'Machine Guard Panel',
    'Industrial Fan Axial', 'Blower Centrifugal', 'Heat Exchanger Plate', 'Cooling Tower Nozzle',
    'Cable Gland Brass', 'Terminal Block DIN', 'Power Supply 24V 10A', 'Industrial Transformer',
    'Welding Helmet Auto', 'Cut Resistant Gloves', 'Safety Goggles Clear', 'Ear Muff Industrial',
    'Tool Cabinet Drawer', 'Work Bench Heavy Duty', 'Machine Vice 150mm', 'Magnetic Base Stand',
    'Laser Distance Meter', 'Digital Caliper 150mm', 'Micrometer Outside', 'Dial Indicator Set',
    'Vacuum Pump Oil', 'Grease Cartridge EP2', 'Cutting Fluid Concentrate', 'Anti Seize Compound',
    'Industrial Seal Kit', 'O-Ring Assortment Metric', 'Gasket Sheet Graphite', 'Mechanical Seal Cartridge',
];

$products = array_slice($products, 0, $limit);

/** @var StoreManagerInterface $storeManager */
$storeManager = $om->get(StoreManagerInterface::class);
$websiteId = (int) $storeManager->getWebsite()->getId();
$storeId = (int) $storeManager->getDefaultStoreView()->getId();

/** @var CategoryCollectionFactory $categoryCollectionFactory */
$categoryCollectionFactory = $om->get(CategoryCollectionFactory::class);
/** @var ProductInterfaceFactory $productFactory */
$productFactory = $om->get(ProductInterfaceFactory::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $om->get(ProductRepositoryInterface::class);
/** @var CategoryLinkManagementInterface $categoryLinkManagement */
$categoryLinkManagement = $om->get(CategoryLinkManagementInterface::class);
/** @var StockRegistryInterface $stockRegistry */
$stockRegistry = $om->get(StockRegistryInterface::class);
/** @var Filesystem $filesystem */
$filesystem = $om->get(Filesystem::class);
/** @var IoFile $ioFile */
$ioFile = $om->get(IoFile::class);

$mediaImport = BP . '/pub/media/import/industrial';
$ioFile->checkAndCreateFolder($mediaImport);

$categoryId = ensureIndustrialCategory($om, $categoryCollectionFactory);
echo "Category ID: {$categoryId}\n";
echo "Creating {$limit} industrial products...\n";

$created = 0;
$updated = 0;
$failed = 0;
$csvRows = [];
$csvRows[] = [
    'sku', 'name', 'price', 'product_type', 'attribute_set_code', 'product_websites',
    'visibility', 'status', 'tax_class_name', 'qty', 'is_in_stock',
    'weight', 'base_image', 'small_image', 'thumbnail_image', 'categories',
];

foreach ($products as $index => $name) {
    $num = $index + 1;
    $sku = sprintf('IND-%03d', $num);
    $imageFile = sprintf('IND-%03d.jpg', $num);
    $imagePath = $mediaImport . '/' . $imageFile;
    $price = round(25 + fmod($num * 7.35, 450) + ($num % 9) * 3.5, 2);

    try {
        if (!$skipDownload || !is_readable($imagePath)) {
            downloadProductImage($imagePath, $sku, $name);
        }

        try {
            $product = $productRepository->get($sku, true, $storeId);
            $isNew = false;
        } catch (NoSuchEntityException) {
            /** @var Product $product */
            $product = $productFactory->create();
            $product->setSku($sku);
            $isNew = true;
        }

        $product->setStoreId(0);
        $product->setName($name);
        $product->setAttributeSetId(4); // Default
        $product->setStatus(Status::STATUS_ENABLED);
        $product->setVisibility(Visibility::VISIBILITY_BOTH);
        $product->setTypeId(Type::TYPE_SIMPLE);
        $product->setPrice($price);
        $product->setWeight(1.0);
        $product->setWebsiteIds([$websiteId]);
        $product->setTaxClassId(2);
        $product->setStockData([
            'use_config_manage_stock' => 1,
            'qty' => 100,
            'is_in_stock' => 1,
        ]);

        $importRelative = '/industrial/' . $imageFile;
        $mediaRelative = 'import/industrial/' . $imageFile;
        $existingImage = (string) $product->getData('image');
        if ($isNew || $existingImage === '' || $existingImage === 'no_selection') {
            $product->addImageToMediaGallery(
                $mediaRelative,
                ['image', 'small_image', 'thumbnail'],
                false,
                false
            );
        }

        $productRepository->save($product);
        $categoryLinkManagement->assignProductToCategories($sku, [$categoryId]);

        $stockItem = $stockRegistry->getStockItemBySku($sku);
        $stockItem->setQty(100);
        $stockItem->setIsInStock(true);
        $stockRegistry->updateStockItemBySku($sku, $stockItem);

        $csvRows[] = [
            $sku,
            $name,
            (string) $price,
            'simple',
            'Default',
            'base',
            'Catalog, Search',
            'Enabled',
            'Taxable Goods',
            '100',
            '1',
            '1',
            $importRelative,
            $importRelative,
            $importRelative,
            'Default Category/Industrial Products',
        ];

        if ($isNew) {
            $created++;
            echo sprintf("[created] %s — %s\n", $sku, $name);
        } else {
            $updated++;
            echo sprintf("[updated] %s — %s\n", $sku, $name);
        }
    } catch (\Throwable $e) {
        $failed++;
        echo sprintf("[fail] %s — %s\n", $sku, $e->getMessage());
    }
}

$csvPath = BP . '/var/import/industrial-products.csv';
$fp = fopen($csvPath, 'w');
if ($fp) {
    foreach ($csvRows as $row) {
        fputcsv($fp, $row, ',', '"', '\\');
    }
    fclose($fp);
    echo "CSV written: {$csvPath}\n";
}

echo sprintf(
    "Done. created=%d updated=%d failed=%d images=%s\n",
    $created,
    $updated,
    $failed,
    $mediaImport
);
echo "Next: php bin/magento klizer:imagerecognition:reindex\n";
exit($failed > 0 ? 1 : 0);

function ensureIndustrialCategory($om, CategoryCollectionFactory $categoryCollectionFactory): int
{
    $collection = $categoryCollectionFactory->create();
    $collection->addAttributeToSelect('name');
    $collection->addAttributeToFilter('name', 'Industrial Products');
    $existing = $collection->getFirstItem();
    if ($existing && $existing->getId()) {
        return (int) $existing->getId();
    }

    /** @var \Magento\Catalog\Model\CategoryFactory $categoryFactory */
    $categoryFactory = $om->get(\Magento\Catalog\Model\CategoryFactory::class);
    $parent = $categoryFactory->create()->load(2);
    $category = $categoryFactory->create();
    $category->setName('Industrial Products');
    $category->setParentId((int) $parent->getId());
    $category->setIsActive(true);
    $category->setIncludeInMenu(true);
    $category->setPath($parent->getPath());
    $category->setAttributeSetId($parent->getDefaultAttributeSetId() ?: 3);
    $category->save();

    return (int) $category->getId();
}

function downloadProductImage(string $targetPath, string $sku, string $name): void
{
    $seed = preg_replace('/[^a-zA-Z0-9]/', '', $sku . $name) ?: $sku;
    $url = 'https://picsum.photos/seed/' . rawurlencode($seed) . '/800/800';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'KlizerIndustrialImport/1.0',
    ]);
    $data = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($data === false || $code >= 400 || $data === '') {
        createPlaceholderImage($targetPath, $sku, $name);
        return;
    }

    if (@file_put_contents($targetPath, $data) === false) {
        createPlaceholderImage($targetPath, $sku, $name);
    }
}

function createPlaceholderImage(string $targetPath, string $sku, string $name): void
{
    $img = imagecreatetruecolor(800, 800);
    if ($img === false) {
        throw new RuntimeException('GD unavailable');
    }

    $hash = crc32($sku);
    $bg = imagecolorallocate($img, 40 + ($hash % 80), 50 + (($hash >> 8) % 80), 60 + (($hash >> 16) % 80));
    $fg = imagecolorallocate($img, 245, 245, 245);
    imagefilledrectangle($img, 0, 0, 800, 800, $bg);

    $lines = [
        'INDUSTRIAL',
        $sku,
        substr($name, 0, 28),
    ];
    $font = findFont();
    $y = 320;
    foreach ($lines as $line) {
        if ($font !== '') {
            imagettftext($img, 24, 0, 60, $y, $fg, $font, $line);
        } else {
            imagestring($img, 5, 40, $y, $line, $fg);
        }
        $y += 50;
    }

    imagejpeg($img, $targetPath, 85);
    imagedestroy($img);
}

function findFont(): string
{
    $candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
    ];
    foreach ($candidates as $font) {
        if (is_readable($font)) {
            return $font;
        }
    }
    return '';
}
