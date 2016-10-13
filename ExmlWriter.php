<?php
/**
 * Class ExmlWriter
 * A simple multi dimensional array to xml,
 * allowing progressive writes on disk
 * @author Chris Hu K.
 */
class ExmlWriter extends \DomDocument
{
    const VERSION = "1.0";

    /**
     * @var \DOMDocument
     */
    private static $xElt;
    /**
     * @var bool
     */
    public $commentOnError = true;
    /**
     * @var resource
     */
    protected $fileHandler;
    /**
     * @var bool|false
     */
    protected $progressive;
    /**
     * @var string
     */
    protected $destFile;
    /**
     * @var string
     */
    protected $rootElt;
    /**
     * @var bool
     */
    protected $writeHead = true;
    /**
     * @var \DOMNode
     */
    protected $root;
    /**
     * @var string
     */
    protected $noname;
    /**
     * @var \DomDocument
     */
    protected $baseDom;
    /**
     * @var string
     */
    private $currentParent;
    /**
     * @var string
     */
    private $nodeClosed;

    /**
     * @var bool
     */
    private $init = false;

    /**
     * @param null $destFile
     * @param null $rootElt
     * @param bool|false $progressive
     * @throws \Exception
     */
    public function __construct($destFile = null, $rootElt = "root", $encoding = "utf-8", $formatting = false, $progressive = false)
    {
        parent::__construct(self::VERSION, $encoding);
        self::$xElt = new \DOMDocument(self::VERSION, $encoding);
        $this->encoding = $encoding;
        $this->formatOutput = $formatting;
        $this->noname = "no_oo";
        $this->rootElt = $rootElt;
        $this->root = $this->appendChild($this->createElement($rootElt));
        $this->destFile = $destFile;
        $this->progressive = $progressive && !empty($destFile);
    }

    /**
     * Adds attribute to root element
     * should be done first if necessary
     * @param $attributeString
     */
    public function rootAddAttribute($attributeString)
    {
        $this->createAttributes($this->root, $attributeString);
    }

    /**
     * Creates attributes from string special format ex:#flag=blue, pipe=cool#
     * @param \DOMNode $dom
     * @param $attributes
     */
    protected function createAttributes(\DOMNode $dom, $attributes)
    {
        if (!empty($attributes)) {
            $found = $this->matchAttributeString($attributes, true);
            foreach ($found as $k => $attr) {
                $dom->setAttribute(key($attr), current($attr));
            }
        }
    }

    /**
     * @param $attr
     * @param bool|false $returnArray
     * @return array|string
     */
    protected function matchAttributeString($attr, $returnArray = false)
    {
        if (preg_match("/#.*#/", $attr, $s) && preg_match("/((\w+=\w+)(\s*,\s*)?)(?R)?/", $attr)) {
            $u = explode(',', str_replace('#', '', $s[0]));

            array_walk($u, function (&$value) use ($returnArray) {
                $r = explode('=', $value);
                $value = $returnArray ? [$r[0] => $r[1]] : ('"' . trim($r[0]) . '"="' . trim($r[1]) . '"');
            });

            if ($returnArray) {
                return $u;
            } elseif ($aOff = preg_replace("/#.*#/", "", $attr)) {
                return $aOff . " " . str_replace(',', ' ', implode(',', array_values($u)));
            }
        }
        return $attr;
    }

    /**
     * initializes Writer
     * @return bool
     * @throws \Exception
     */
    protected function init()
    {
        if (!empty($this->destFile)) {
            if (!$this->fileHandler = fopen($this->destFile, 'a')) {
                throw new \Exception("Failed to open File : " . $this->destFile);
            }

            if ($this->progressive) {
                $this->write($this->getHead());
            }
        }
        return true;
    }

    /**
     * Writes on disk
     * @param $data
     * @param bool $final
     * @return bool
     * @throws \Exception
     */
    protected function write($data, $final = false)
    {
        $data = (string)$data;
        if (strlen($data) > 0 && fwrite($this->fileHandler, $data) === false) {
            throw new \Exception("Failed to write to File...");
        }
        if ($final) {
            fclose($this->fileHandler);
        }
        return true;
    }

    /**
     * Gets current xml head
     * @return string
     */
    protected function getHead()
    {
        return $this->formatOutput ?  $this->saveXML() . PHP_EOL :  $this->saveXML();
    }

    /**
     * Finalizes writing
     */
    public function finalize()
    {
        if (!$this->progressive && !empty($this->destFile)) {
            $this->save($this->destFile);
        } else {
            if ($this->currentParent !== $this->nodeClosed) {
                $this->write(
                    $this->closeNode($this->currentParent)
                );
            }
            $this->write("</" . $this->rootElt . ">" . PHP_EOL, true);
        }
    }

    /**
     * @param $node
     * @return string
     */
    protected function closeNode($node)
    {
        $this->nodeClosed = $node;
        return empty($node) ? '' : $this->getNodeString(array_reverse(explode('>', $node), true), '/');
    }

    /**
     * @param $exploded
     * @param string $close
     * @return string
     */
    protected function getNodeString($exploded, $close = '')
    {
        $res = "\n";
        $end = $this->formatOutput ? "\n" : '';
        foreach ($exploded as $k => $v) {
            $v = $this->buildNodeAttribute($v, $close);
            $res .= str_repeat("\t", $k) . "<$close" . trim($v) . ">$end";
        }

        return $res;
    }

    /**
     * @param $attribute
     * @return mixed
     */
    protected function buildNodeAttribute($node, $close)
    {
        if (!empty($close) && ($aOff = preg_replace("/#.*#/", "", $node))) {
            return $aOff;
        }

        return $this->matchAttributeString($node);
    }

    /**
     * @param $data
     * @param string $parentNode useable only in progressive mode
     * @return bool
     * @throws \Exception
     */
    public function append($data, $parentNode = "")
    {
        if (!$this->init) {
            $this->init = $this->init();
        }

        if ($this->progressive) {

            if (!empty($parentNode)) {
                if ($this->currentParent !== $parentNode) {
                    $this->write(
                        $this->closeNode($this->currentParent)
                    );
                    $this->write(
                        $this->openNode($parentNode)
                    );
                    $this->currentParent = $parentNode;
                }
            }


            $this->baseDom = $this->getCopy();
            $this->createNode($data, $this->baseDom->root, $this->baseDom);

            $node = $this->headOff(
                $this->baseDom->saveXML(),
                $this->noname
            );

            $this->baseDom = null;
            return $this->write($node);
        }

        $this->createNode($data, $this->root, $this);
    }

    /**
     * @param $node
     * @return string
     */
    protected function openNode($node)
    {
        return empty($node) ? '' : $this->getNodeString(explode('>', $node));
    }

    /**
     * @return \DOMDocument
     */
    protected function getCopy()
    {
        $tmp = clone self::$xElt;
        $tmp->root = $tmp->appendChild($tmp->createElement($this->noname));
        $tmp->formatOutput = $this->formatOutput;
        return $tmp;
    }

    /**
     * Creates Node
     * @param $arr
     * @param $node
     * @param $base
     * @throws \Exception
     */
    protected function createNode($arr, $node, $base)
    {
        $previous = $node;
        foreach ($arr as $element => $value) {
            try {
                $element = is_numeric($element) ? $this->noname : $element;

                if ($element == "__attr__") {
                    $currentAttribute = $value;
                    continue;
                }

                if (!empty($value)) {
                    $child = $base->createElement($element, (is_array($value)
                        ? null
                        : $this->escapeString($value)));
                    if (!empty($currentAttribute)) {
                        foreach ($currentAttribute as $k => $v) {
                            $attr = $base->createAttribute($k);
                            $attr->value = $v;
                            $previous->appendChild($attr);
                        }
                        $currentAttribute = $previous = null;
                    }
                    $node->appendChild($child);

                    if (is_array($value)) {
                        $this->createNode($value, $child, $base);
                    }
                }
            } catch (\Exception $e) {
                if ($this->commentOnError) {
                    $msg = " Failed " . $e->getMessage() . PHP_EOL . json_encode($value) . PHP_EOL;
                    $child = $base->createComment($msg);
                    $node->appendChild($child);
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Protects from unterminated entity reference
     * @param $value
     * @return null|string
     */
    protected function escapeString($value)
    {
        return is_null($value) ? null : htmlspecialchars($value, ENT_XML1, $this->encoding);
    }

    /**
     * @param $xml
     * @param string $root
     * @param null $noNameOff
     * @return mixed
     */
    protected function headOff($xml, $root = "Feed", $noNameOff = null)
    {
        $pat = [];
        if ($noNameOff) {
            $pat = ["/(<$noNameOff>|<\/$noNameOff>)/"];
        }
        $pat = array_merge($pat, ["/<\?.*\?>/", "/(<$root>|<\/$root>)/", "/^\s+$/"]);

        return ($res = preg_replace($pat, "", $xml)) ? $res : $xml;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->saveXML();
    }
}