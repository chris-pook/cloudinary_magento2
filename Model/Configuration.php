<?php

namespace Cloudinary\Cloudinary\Model;

use Cloudinary\Cloudinary\Core\AutoUploadMapping\AutoUploadConfigurationInterface;
use Cloudinary\Cloudinary\Core\Cloud;
use Cloudinary\Cloudinary\Core\ConfigurationInterface;
use Cloudinary\Cloudinary\Core\Credentials;
use Cloudinary\Cloudinary\Core\Exception\InvalidCredentials;
use Cloudinary\Cloudinary\Core\Image\Transformation;
use Cloudinary\Cloudinary\Core\Image\Transformation\Dpr;
use Cloudinary\Cloudinary\Core\Image\Transformation\FetchFormat;
use Cloudinary\Cloudinary\Core\Image\Transformation\Freeform;
use Cloudinary\Cloudinary\Core\Image\Transformation\Gravity;
use Cloudinary\Cloudinary\Core\Image\Transformation\Quality;
use Cloudinary\Cloudinary\Core\Security\CloudinaryEnvironmentVariable;
use Cloudinary\Cloudinary\Core\UploadConfig;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\StoreManagerInterface;

class Configuration implements ConfigurationInterface
{
    //= Basics
    const CONFIG_PATH_ENABLED = 'cloudinary/cloud/cloudinary_enabled';
    const CONFIG_PATH_ENVIRONMENT_VARIABLE = 'cloudinary/setup/cloudinary_environment_variable';
    const CONFIG_PATH_AUTOMATIC_LOGIN_USER = 'cloudinary/setup/cloudinary_automatic_login_user';
    const CONFIG_PATH_CDN_SUBDOMAIN = 'cloudinary/configuration/cloudinary_cdn_subdomain';

    //= Transformations
    const CONFIG_PATH_DEFAULT_GRAVITY = 'cloudinary/transformations/cloudinary_gravity';
    const CONFIG_PATH_DEFAULT_QUALITY = 'cloudinary/transformations/cloudinary_image_quality';
    const CONFIG_PATH_DEFAULT_DPR = 'cloudinary/transformations/cloudinary_image_dpr';
    const CONFIG_PATH_DEFAULT_FETCH_FORMAT = 'cloudinary/transformations/cloudinary_fetch_format';
    const CONFIG_PATH_GLOBAL_FREEFORM = 'cloudinary/transformations/cloudinary_free_transform_global';

    //= Advanced
    const CONFIG_PATH_REMOVE_VERSION_NUMBER = 'cloudinary/advanced/remove_version_number';
    const CONFIG_PATH_USE_ROOT_PATH = 'cloudinary/advanced/use_root_path';

    //= Product Gallery
    const CONFIG_PATH_PG_ALL = 'cloudinary/product_gallery';
    const CONFIG_PATH_PG_ENABLED = 'cloudinary/product_gallery/enabled';
    const CONFIG_PATH_PG_THEMEPROPS_PRIMARY = 'cloudinary/product_gallery/themeProps_primary';
    const CONFIG_PATH_PG_THEMEPROPS_ONPRIMARY = 'cloudinary/product_gallery/themeProps_onPrimary';
    const CONFIG_PATH_PG_THEMEPROPS_ACTIVE = 'cloudinary/product_gallery/themeProps_active';
    const CONFIG_PATH_PG_THEMEPROPS_ONACTIVE = 'cloudinary/product_gallery/themeProps_onActive';
    const CONFIG_PATH_PG_TRANSITION = 'cloudinary/product_gallery/transition';
    const CONFIG_PATH_PG_ASPECT_RATIO = 'cloudinary/product_gallery/aspectRatio';
    const CONFIG_PATH_PG_ZOOMPROPS_NAVIGATION = 'cloudinary/product_gallery/navigation';
    const CONFIG_PATH_PG_ZOOM = 'cloudinary/product_gallery/zoom';
    const CONFIG_PATH_PG_ZOOMPROPS_TYPE = 'cloudinary/product_gallery/zoomProps_type';
    const CONFIG_PATH_PG_ZOOMPROPS_POSITION = 'cloudinary/product_gallery/zoomPropsViewerPosition';
    const CONFIG_PATH_PG_ZOOMPROPS_TRIGGER = 'cloudinary/product_gallery/zoomProps_trigger';
    const CONFIG_PATH_PG_CAROUSEL_LOCATION = 'cloudinary/product_gallery/carouselLocation';
    const CONFIG_PATH_PG_CAROUSEL_OFFSET = 'cloudinary/product_gallery/carouselOffset';
    const CONFIG_PATH_PG_CAROUSEL_STYLE = 'cloudinary/product_gallery/carouselStyle';
    const CONFIG_PATH_PG_THUMBNAILPROPS_WIDTH = 'cloudinary/product_gallery/thumbnailProps_width';
    const CONFIG_PATH_PG_THUMBNAILPROPS_HEIGHT = 'cloudinary/product_gallery/thumbnailProps_height';
    const CONFIG_PATH_PG_THUMBNAILPROPS_NAVIGATION_SHAPE = 'cloudinary/product_gallery/thumbnailProps_navigationShape';
    const CONFIG_PATH_PG_THUMBNAILPROPS_SELECTED_STYLE = 'cloudinary/product_gallery/thumbnailProps_selectedStyle';
    const CONFIG_PATH_PG_THUMBNAILPROPS_SELECTED_BORDER_POSITION = 'cloudinary/product_gallery/thumbnailProps_selectedBorderPosition';
    const CONFIG_PATH_PG_THUMBNAILPROPS_SELECTED_BORDER_WIDTH = 'cloudinary/product_gallery/thumbnailProps_selectedBorderWidth';
    const CONFIG_PATH_PG_THUMBNAILPROPS_MEDIA_ICON_SHAPE = 'cloudinary/product_gallery/thumbnailProps_mediaSymbolShape';
    const CONFIG_PATH_PG_INDICATORPROPS_SHAPE = 'cloudinary/product_gallery/indicatorProps_shape';
    const CONFIG_PATH_PG_CUSTOM_FREE_PARAMS = 'cloudinary/product_gallery/custom_free_params';

    //= Others
    const CONFIG_PATH_SECURE_BASE_URL = "web/secure/base_url";
    const CONFIG_PATH_UNSECURE_BASE_URL = "web/unsecure/base_url";
    const CONFIG_PATH_USE_SECURE_IN_FRONTEND = "web/secure/use_in_frontend";
    const CONFIG_PATH_USE_SIGNED_URLS = 'cloudinary/advanced/use_signed_urls';

    const USER_PLATFORM_TEMPLATE = 'CloudinaryMagento/%s (Magento %s)';
    const USE_FILENAME = true;
    const UNIQUE_FILENAME = false;
    const OVERWRITE = false;
    const SCOPE_ID_ONE = 1;
    const SCOPE_ID_ZERO = 0;

    /**
     * @var ScopeConfigInterface
     */
    private $configReader;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var EncryptorInterface
     */
    private $decryptor;

    /**
     * @var CloudinaryEnvironmentVariable
     */
    private $environmentVariable;

    /**
     * @var AutoUploadConfigurationInterface
     */
    private $autoUploadConfiguration;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ScopeConfigInterface             $configReader
     * @param WriterInterface                  $configWriter
     * @param EncryptorInterface               $decryptor
     * @param AutoUploadConfigurationInterface $autoUploadConfiguration
     * @param StoreManagerInterface            $storeManager
     */
    public function __construct(
        ScopeConfigInterface $configReader,
        WriterInterface $configWriter,
        EncryptorInterface $decryptor,
        AutoUploadConfigurationInterface $autoUploadConfiguration,
        \Psr\Log\LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->configReader = $configReader;
        $this->configWriter = $configWriter;
        $this->decryptor = $decryptor;
        $this->autoUploadConfiguration = $autoUploadConfiguration;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * @return StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * @return Cloud
     */
    public function getCloud()
    {
        return $this->getEnvironmentVariable()->getCloud();
    }

    /**
     * @return Credentials
     */
    public function getCredentials()
    {
        return $this->getEnvironmentVariable()->getCredentials();
    }

    /**
     * @return string
     */
    public function getAutomaticLoginUser()
    {
        return (string) $this->configReader->getValue(self::CONFIG_PATH_AUTOMATIC_LOGIN_USER);
    }

    /**
     * @return Transformation
     */
    public function getDefaultTransformation()
    {
        return Transformation::builder()
            ->withGravity(Gravity::fromString($this->getDefaultGravity()))
            ->withQuality(Quality::fromString($this->getImageQuality()))
            ->withFetchFormat(FetchFormat::fromString($this->getFetchFormat()))
            ->withFreeform(Freeform::fromString($this->getDefaultGlobalFreeform()))
            ->withDpr(Dpr::fromString($this->getImageDpr()));
    }

    /**
     * @return string
     */
    private function getDefaultGlobalFreeform()
    {
        return (string) $this->configReader->getValue(self::CONFIG_PATH_GLOBAL_FREEFORM);
    }

    /**
     * @return boolean
     */
    public function getCdnSubdomainStatus()
    {
        return $this->configReader->isSetFlag(self::CONFIG_PATH_CDN_SUBDOMAIN);
    }

    /**
     * @return string
     */
    public function getUserPlatform()
    {
        return sprintf(self::USER_PLATFORM_TEMPLATE, '1.9.0', '2.0.0');
    }

    /**
     * @return UploadConfig
     */
    public function getUploadConfig()
    {
        return UploadConfig::fromBooleanValues(self::USE_FILENAME, self::UNIQUE_FILENAME, self::OVERWRITE);
    }

    /**
     * @return boolean
     */
    public function isEnabled($checkEnvVar = true)
    {
        return ($this->hasEnvironmentVariable() || !$checkEnvVar) && $this->configReader->isSetFlag(self::CONFIG_PATH_ENABLED);
    }

    public function enable()
    {
        $this->configWriter->save(self::CONFIG_PATH_ENABLED, self::SCOPE_ID_ONE);
    }

    public function disable()
    {
        $this->configWriter->save(self::CONFIG_PATH_ENABLED, self::SCOPE_ID_ZERO);
    }

    /**
     * @return array
     */
    public function getFormatsToPreserve()
    {
        return ['png', 'webp', 'gif', 'svg'];
    }

    /**
     * @param  string $file
     * @return string
     */
    public function getMigratedPath($file)
    {
        return $this->autoUploadConfiguration->isActive() ? sprintf('%s/%s', DirectoryList::MEDIA, $file) : $file;
    }

    /**
     * @return string
     */
    public function getDefaultGravity()
    {
        return (string) $this->configReader->getValue(self::CONFIG_PATH_DEFAULT_GRAVITY);
    }

    /**
     * @return string
     */
    public function getFetchFormat()
    {
        return $this->configReader->isSetFlag(self::CONFIG_PATH_DEFAULT_FETCH_FORMAT) ? FetchFormat::FETCH_FORMAT_AUTO : '';
    }

    /**
     * @return string
     */
    public function getImageQuality()
    {
        return (string) $this->configReader->getValue(self::CONFIG_PATH_DEFAULT_QUALITY);
    }

    /**
     * @return string
     */
    public function getImageDpr()
    {
        return $this->configReader->getValue(self::CONFIG_PATH_DEFAULT_DPR);
    }

    /**
     * @return bool
     */
    public function hasEnvironmentVariable()
    {
        return (bool)$this->configReader->getValue(self::CONFIG_PATH_ENVIRONMENT_VARIABLE);
    }

    /**
     * @return CloudinaryEnvironmentVariable
     */
    public function getEnvironmentVariable()
    {
        if (is_null($this->environmentVariable)) {
            try {
                $this->environmentVariable = CloudinaryEnvironmentVariable::fromString(
                    $this->decryptor->decrypt(
                        $this->configReader->getValue(self::CONFIG_PATH_ENVIRONMENT_VARIABLE)
                    )
                );
            } catch (InvalidCredentials $invalidConfigException) {
                $this->logger->critical($invalidConfigException);
            }
        }

        return $this->environmentVariable;
    }

    /**
     * @return bool
     */
    public function isEnabledProductGallery()
    {
        return (bool) $this->configReader->getValue(self::CONFIG_PATH_PG_ENABLED);
    }

    /**
     * @return array
     */
    public function getProductGalleryAll()
    {
        return (array) $this->configReader->getValue(self::CONFIG_PATH_PG_ALL);
    }

    /**
     * @return bool
     */
    public function getRemoveVersionNumber()
    {
        return (bool) $this->configReader->getValue(self::CONFIG_PATH_REMOVE_VERSION_NUMBER);
    }

    /**
     * @return bool
     */
    public function getUseRootPath()
    {
        return (bool) $this->configReader->getValue(self::CONFIG_PATH_USE_ROOT_PATH);
    }

    /**
     * @return bool
     */
    public function getUseSignedUrls()
    {
        return (bool) $this->configReader->getValue(self::CONFIG_PATH_USE_SIGNED_URLS);
    }

    /**
     * @method getMediaBaseUrl
     * @return string
     */
    public function getMediaBaseUrl()
    {
        return $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
    }
}
