<?php
namespace IISH\Content\Covers;
use Zend\Config\Config;
use VuFind\Content\AbstractCover;
use IISH\Content\ResizableCover;
use IISH\Content\AccessClosedCover;

/**
 * IISH cover content loader.
 *
 * @package IISH\Content\Covers
 */
class IISH extends AbstractCover implements ResizableCover, AccessClosedCover {
    /**
     * @var string
     */
    private $accessToken;

    /**
     * Constructor.
     *
     * @param Config $iishConfig The IISH configuration.
     */
    public function __construct(Config $iishConfig) {
        // Obtain the content access token
        $contentAccessToken = new IISHContentAccessToken($iishConfig);
        $this->accessToken = $contentAccessToken->getAccessToken();
    }

    /**
     * Retrieve an audio\visual from the IISH.
     * The interpretation is the handle: http://hdl.handle.net/10622/[pid]?locatt=view":[size]
     * We append an access token if the request comes from a known able network that is 'ours'.
     *
     * @param string $key  The API key.
     * @param string $size Size of image to load (small/medium/large).
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     *                     pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     *                     to an OCLC number string).
     *
     * @return string|bool The URL, or false if no URL can be obtained.
     */
    public function getUrl($key, $size, $ids) {
        if (!isset($ids['pid'])) {
            return false;
        }

        switch ($size) {
            case 'small':
                $imageIndex = 'level3';
                break;
            case 'large':
            case 'medium':
            default :
                $imageIndex = 'level2';
        }

        // If we have obtained an access token, append it to the URL
        $accessTokenURL = '';
        if (!empty($this->accessToken)) {
            $accessTokenURL = '&urlappend=?access_token=' . $this->accessToken;
        }

        return 'http://hdl.handle.net/10622/' . $ids['pid'] . '?locatt=view:' . $imageIndex . $accessTokenURL;
    }

    /**
     * Does this plugin support the provided ID array?
     *
     * Override to add support for PIDs.
     *
     * @param array $ids IDs that will later be sent to getUrl().
     *
     * @return bool Whether this cover content loader supports one
     */
    public function supports($ids) {
        return (parent::supports($ids) || isset($ids['pid']));
    }

    /**
     * Cover image should resize to width returned.
     * The images from this domain are a little bit too large, so we resize them to get a uniform width.
     *
     * @param string $key  The API key.
     * @param string $size Size of image to load (small/medium/large).
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     *                     pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     *                     to an OCLC number string).
     *
     * @return int|null The width to resize to.
     */
    public function resizeToWidthFor($key, $size, $ids) {
        $reductionSize = null;
        if (($size !== 'small') && ($size !== 'large')) {
            $reductionSize = 275;
        }

        return $reductionSize;
    }

    /**
     * Determines whether access is closed for the given image.
     *
     * @param string      $key         The API key.
     * @param string      $size        Size of image to load (small/medium/large).
     * @param array       $ids         Associative array of identifiers (keys may include 'isbn'
     *                                 pointing to an ISBN object, 'issn' pointing to a string and 'oclc' pointing
     *                                 to an OCLC number string).
     * @param string|null $publication The publication parameter.
     *
     * @return bool Whether access is closed.
     */
    public function isAccessClosed($key, $size, $ids, $publication = null) {
        return (empty($this->accessToken) && ($publication === 'closed'));
    }
}