<?php
namespace IISH\RecordDriver;
use VuFind\RecordDriver\SolrMarc as VuFindSolrMarc;

/**
 * Model for MARC records in Solr.
 *
 * @package IISH\RecordDriver
 */
class SolrMarc extends VuFindSolrMarc {
    /**
     * @var \Zend\Config\Config
     */
    protected $iishConfig;

    /**
     * Constructor.
     *
     * @param \Zend\Config\Config $mainConfig     VuFind main configuration. (omit for
     *                                            built-in defaults)
     * @param \Zend\Config\Config $recordConfig   Record-specific configuration file.
     *                                            (omit to use $mainConfig as $recordConfig)
     * @param \Zend\Config\Config $searchSettings Search-specific configuration file
     * @param \Zend\Config\Config $iishConfig     IISH specific configuration.
     */
    public function __construct($mainConfig = null, $recordConfig = null, $searchSettings = null, $iishConfig = null) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings);
        $this->iishConfig = $iishConfig;
    }

    /**
     * We don't use the built-in AJAX status lookups.
     */
    public function supportsAjaxStatus() {
        return false;
    }

    /**
     * Get the short (pre-subtitle) title of the record.
     * If the title ends with a single character, remove it. (Usually /)
     *
     * @return string The short title escaped.
     */
    public function getShortTitleEscaped() {
        return preg_replace('/\s.\Z/', '', $this::getShortTitle());
    }

    /**
     * Get text that can be displayed to represent this record in breadcrumbs.
     *
     * Override to make sure we use the escaped short title.
     *
     * @return string Breadcrumb text to represent this record.
     */
    public function getBreadcrumb() {
        return $this->getShortTitleEscaped();
    }

    /**
     * True if we have a link to downloadable content.
     *
     * @return bool
     */
    public function getDownloadable() {
        if (isset($this->fields['downloadable'])) {
            return $this->fields['downloadable'];
        }

        return false;
    }

    /**
     * TODO: Previously used field 'callnumber-a'.
     *
     * Returns the collector.
     *
     * @return string|null The collector.
     */
    public function getCollector() {
        if (isset($this->fields['collector'])) {
            return $this->fields['collector'];
        }

        return null;
    }

    /**
     * Retrieve the publication status.
     * Defaults to: 'closed' with 852$p=30051* ; 'open' in any other case.
     *
     * @return string The publication status.
     */
    public function getPublicationStatus() {
        $publicationStatus = $this->getFirstFieldValue('542', array('m'));
        if (empty($publicationStatus)) {
            $p = $this->getFirstFieldValue('852', array('p'));
            $publicationStatus = (strpos($p, '30051') === false) ? 'open' : 'closed';
        }

        return $publicationStatus;
    }

    /**
     * Returns an extension of the title.
     *
     * @return string An extension of the title.
     */
    public function getTitleExtension() {
        $title = $this->getTitle();
        $append = null;
        $fields = array('245' => array('b'), '710' => array('a'));

        foreach ($fields as $field => $subfield) {
            $append = $this->getFirstFieldValue($field, $subfield);
            if ($append && (strpos($title, $append) === false)) {
                return ' ' . $append;
            }
        }

        return '';
    }

    /**
     * Does this record have audio content available?
     *
     * @return bool True if there is audio content.
     */
    public function hasAudio() {
        return (strpos($this->getFirstFieldValue('856', array('q')), 'audio') === 0);
    }

    /**
     * Does this record have video content available?
     *
     * @return bool True if there is video content.
     */
    public function hasVideo() {
        return (strpos($this->getFirstFieldValue('856', array('q')), 'video') === 0);
    }

    /**
     * Tries to find the authority for the specified MARC field.
     * If a value is given, the authority for that value is returned if found.
     * Otherwise the first found authority is returned instead.
     * If a subfield is given, the value is matched with the value of the given subfield.
     * Otherwise the first subfield (a) is used instead.
     *
     * @param string      $field    The MARC field
     * @param string|null $value    The value of the given subfield for matching
     * @param string|null $subfield The MARC subfield
     *
     * @return string|null The authority or null if not found
     */
    public function getAuthorityForField($field, $value = null, $subfield = null) {
        $subfields = ($subfield === null) ? array('a', '0') : array($subfield, '0');
        $fields = $this->marcRecord->getFields($field);

        foreach ($fields as $f) {
            $subfieldValues = $this->getSubfieldArray($f, $subfields, false);
            if (($value == null) || (($value != null) && ($subfieldValues[0] == $value))) {
                return isset($subfieldValues[1]) ? $subfieldValues[1] : null;
            }
        }

        return null;
    }

    /**
     * Tries to find the values and the authorities for the specified MARC field.
     * If no subfield is given, the first subfield (a) is used instead.
     *
     * @param string      $field    The MARC field
     * @param string|null $subfield The MARC subfield
     *
     * @return array|null An array with the 'value' and the 'authority'
     */
    public function getAllAuthorityForField($field, $subfield = null) {
        $results = array();
        $fields = $this->marcRecord->getFields($field);
        $subfields = ($subfield === null) ? array('a', '0') : array($subfield, '0');

        foreach ($fields as $f) {
            $subfieldValues = $this->getSubfieldArray($f, $subfields, false);
            $value = (isset($subfieldValues[0])) ? $subfieldValues[0] : null;
            $authority = (isset($subfieldValues[1])) ? $subfieldValues[1] : null;
            $results[] = array('value' => $value, 'authority' => $authority);
        }

        return $results;
    }

    /**
     * Determine if this is the journal known as the NEHA.
     *
     * @return bool True if this is the journal known as the NEHA.
     */
    public function isIRSH() {
        $u = $this->getFirstFieldValue('856', array('u'));

        return (preg_match('/^http:\/\/hdl\.handle\.net\/10622\/\d{8}-\d{4}-\d{3}$/', $u) == 1);
    }

    /**
     * Groups all author names and links under the same MARC type:
     * [type1: [[name: author1, link: author1], [name: author2, link: author2], [name: author3, link: author3]],
     *  type2: [[name: author4, link: author4], [name: author5, link: author5]]]
     *
     * @return array All author names and links grouped under the same MARC type.
     */
    public function getAuthorship() {
        $authors = array();
        foreach (array(100, 110, 111, 700, 710, 711) as $tag) {
            $key = 'author' . $tag;
            $fields = $this->marcRecord->getFields($tag);
            foreach ($fields as $field) {
                $subfields = $field->getSubfields();

                $link = '';
                $name = '';
                $role = $key;

                foreach ($subfields as $subfield) {
                    switch ($subfield->getCode()) {
                        case 'a':
                            $link = $subfield->getData();
                        case 'b':
                        case 'c':
                        case 'd':
                            $name = $name . $subfield->getData() . ' ';
                            break;
                        case 'e':
                            $role = $this->normalize($subfield->getData());
                            break;
                    }
                }

                if ($name) {
                    $item = array('name' => $this->normalize($name), 'link' => $link);
                    if (isset($authors[$role])) {
                        array_push($authors[$role], $item);
                    }
                    else {
                        $authors[$role] = array($item);
                    }
                }
            }
        }

        return $authors;
    }

    /**
     * Returns the PID that refers to this record in Search.
     *
     * @return string|null The PID that refers to this record in Search.
     */
    public function getIsShownAt() {
        return $this->getFirstFieldValue('902', array('a'));
    }

    /**
     * Returns the PID that refers to digital material belonging to this record.
     *
     * @return string|null The PID that refers to digital material belonging to this record.
     */
    public function getIsShownBy() {
        $p = $this->getFirstFieldValue('852', array('p'));
        $pos = strpos($p, '30051');
        if ($pos === false) {
            $j = $this->getFirstFieldValue('852', array('j'));
            if ($j == "Embedded") {
                $u = $this->getFirstFieldValue('856', array('u'));
                if ($u) {
                    $pos = strpos($u, '/10622/');

                    return ($pos === false) ? null : substr($u, $pos + 7);
                }
            }
        }

        return ($pos === false) ? null : $p;
    }

    /**
     * Returns the copyright 'A' field.
     *
     * @return string The copyright 'a' field.
     */
    public function getCopyrightA() {
        return $this->getFirstFieldValue('540', array('a'));
    }

    /**
     * Returns the copyright 'B' field.
     *
     * @return string The copyright 'b' field.
     */
    public function getCopyrightB() {
        return $this->getFirstFieldValue('540', array('b'));
    }

    /**
     * Returns the journal.
     *
     * @return string|null The journal.
     */
    public function getJournal() {
        return $this->getFirstFieldValue('730', array('a'));
    }

    /**
     * Returns all the holdings.
     *
     * @return array All the holdings.
     */
    public function getHoldings() {
        $holdings = array();
        $i = 1;
        $key = null;
        $datafields = $this->marcRecord->getFields();

        foreach ($datafields as $datafield) {
            $tag = $datafield->getTag();

            if ($tag == '852') {
                $subfieldc = $datafield->getSubfield('c');
                $subfieldj = $datafield->getSubfield('j');
                $subfield = null;

                if ($subfieldc && $subfieldj) {
                    $subfield = $subfieldc->getData() . ' ' . $subfieldj->getData();
                }
                if ($subfieldc && !$subfieldj) {
                    $subfield = $subfieldc->getData();
                }
                if (!$subfieldc && $subfieldj) {
                    $subfield = $subfieldj->getData();
                }
                if ($subfield) {
                    $key = $i++;
                    $holdings[$key]['c'] = $subfield;
                    if ($subfieldj) {
                        $holdings[$key]['j'] = $subfieldj->getData();
                    }
                }
            }

            if ($tag == '866' && $key) {
                $subfield = $datafield->getSubfield('a');
                if ($subfield) {
                    $holdings[$key]['note'] = $subfield->getData();
                }
            }
            else if ($tag == '866' && !$key) {
                print('Key was null '); // TODO: Print?
            }
        }

        return $holdings;
    }

    /**
     * Returns the core classification.
     *
     * @return array|null The core classification.
     */
    public function getCoreClassification() {
        $classifications = $this->marcRecord->getFields('690');
        if ($classifications) {
            foreach ($classifications as $classification) {
                // Is there an address in the current field?
                $marcA = $classification->getSubfield('a');
                $marcB = $classification->getSubfield('b');
                if ($marcA != null || $marcB != null) {
                    $c = array();
                    if ($marcA) {
                        $c[] = $marcA->getData();
                    }
                    if ($marcB) {
                        $c[] = $marcB->getData();
                    }

                    return $c;
                }
            }
        }

        return null;
    }

    /**
     * Returns the article.
     *
     * @return string The article.
     */
    public function getArticle() {
        $a = $this->getFirstFieldValue('773', array('a'));
        $t = $this->getFirstFieldValue('773', array('t'));
        $g = $this->getFirstFieldValue('773', array('g'));

        if ($a && $g) {
            return $a . ', ' . $g;
        }
        if ($t && $g) {
            return $t . ', ' . $g;
        }
        if ($a) {
            return $a;
        }

        return ($t) ? $t : $g;
    }

    /**
     * Returns the extended date span publisher.
     *
     * @return string The extended date span publisher.
     */
    public function getExtendedDateSpanPublisher() {
        $e = $this->getFirstFieldValue('260', array('e'));
        $f = $this->getFirstFieldValue('260', array('f'));

        if ($e && $f) {
            return $e . $f;
        }

        return ($e) ? $e : $f;
    }

    /**
     * Returns the URL that resolves to the image resource.
     *
     * @return string|null The URL that resolves to the image resource.
     */
    public function getImageURL() {
        $pid = $this->getIsShownBy();
        if (!empty($pid)) {
            $url = 'http://hdl.handle.net/10622/' . $pid;

            switch ($this->getLargestPossibleSize()) {
                case 'large':
                    return $url;
                case 'small':
                    return $url . '?locatt=view:level3';
                case 'medium':
                default:
                    return $url . '?locatt=view:level2';
            }
        }

        return null;
    }

    /**
     * Determine the largest possible image size, based on the publication status.
     *
     * @param string $largestSize The largest possible size anyway.
     *
     * @return string The largest possible image size.
     */
    public function getLargestPossibleSize($largestSize = 'large') {
        switch ($this->getPublicationStatus()) {
            case 'closed':
                return $largestSize;
            case 'minimal':
                return 'small';
            case 'restricted':
            default:
                return ($largestSize === 'small') ? $largestSize : 'medium';
        }
    }

    /**
     * Returns an OAI identifier reference.
     * Either the Solr ID or the value of the 902$a field.
     *
     * @return string An OAI identifier reference.
     */
    public function getOAIPid() {
        $pid = $this->getFirstFieldValue('902', array('a'));
        // TODO: Find a better way to determine the OAI identifier. Could introduce a 903$a.
        $id = (strlen($pid) === 42) ? $this->getUniqueID() : $pid;

        $oaiPrefix = isset($this->iishConfig->OAI->prefix)
            ? $this->iishConfig->OAI->prefix
            : 'oai:socialhistoryservices.org:';

        return $oaiPrefix . $id;
    }

    /**
     * Returns the main author role.
     *
     * @return string The main author role.
     */
    public function getMainAuthorRole() {
        return $this->getFirstFieldValue('100', array('e'));
    }

    /**
     * Get the publication dates of the record.
     * See also getDateSpan().
     *
     * Override with the 'original' MARC record value to prevent translations.
     * Example: return original [190?] value vs. 1900 as translated in the field 'publishDate'.
     *
     * @return array
     */
    public function getPublicationDates() {
        return $this->getFieldArray('260', array('c'));
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getUrls() {
        $urls = parent::getUrls();

        if ($this->isIRSH()) {
            foreach ($urls as $key => $url) {
                $address = $url['url'];

                // Find out if the URL contains a query part, if so, replace it
                $pos = strpos($address, '?');
                if ($pos !== false) {
                    $address = substr_replace($address, '?locatt=view:master', $pos);
                }
                else {
                    $address = $address . '?locatt=view:master';
                }

                if ($url['url'] === $url['desc']) {
                    $urls[$key]['desc'] = $address;
                }
                $urls[$key]['url'] = $address;
            }
        }

        return $urls;
    }

    /**
     * Returns one of three things: a full URL to a thumbnail preview of the record
     * if an image is available in an external system; an array of parameters to
     * send to VuFind's internal cover generator if no fixed URL exists; or false
     * if no thumbnail can be generated.
     *
     * @param string $size Size of thumbnail (small, medium or large -- small is default).
     *
     * @return string|array|bool
     */
    public function getThumbnail($size = 'small') {
        $thumbnail = parent::getThumbnail($size);

        if ($pid = $this->getAudioVisualPid()) {
            $thumbnail = is_array($thumbnail) ? $thumbnail : array();

            $thumbnail['pid'] = $pid;
            $thumbnail['size'] = $this->getLargestPossibleSize($size);
            $thumbnail['publication'] = $this->getPublicationStatus();
        }

        return $thumbnail;
    }

    /**
     * Extract the barcode or PID from the URLs for this record.
     *
     * @return bool|string The barcode or PID, if found.
     */
    private function getAudioVisualPid() {
        $urls = $this->getURLs();
        foreach ($urls as $url) {
            $pos = strpos($url['url'], '/10622/');
            if ($pos > 1) {
                $tmp = substr($url['url'], $pos + 7);
                $pos = strpos($tmp, '?');

                return ($pos) ? substr($tmp, 0, $pos) : $tmp;
            }
        }

        return false;
    }

    /**
     * Normalize the given text:
     * - Trim the text.
     * - Remove the '.' or ',' at the end.
     * - Make sure the first character is uppercase.
     *
     * @param string $text The text to normalize.
     *
     * @return string The normalized text.
     */
    private function normalize($text) {
        $text = trim($text);
        $i = strlen($text) - 1;
        if ($text[$i] === '.' || $text[$i] === ',') {
            $text = substr($text, 0, $i);
        }

        return ucfirst($text);
    }
}