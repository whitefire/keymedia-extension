<?php
/**
 * KeyMedia eZ extension
 *
 * @copyright     Copyright 2011, Keyteq AS (http://keyteq.no/labs)
 */

namespace keymedia\modules\key_media;

use \eZPersistentObject;
use \keymedia\models\Backend;
use \keymedia\models\media\Handler;

use \eZHTTPFile;
use \eZHTTPTool;
use \eZContentObjectAttribute;

/**
 * KeyMedia controller
 * Exposes the HTTP actions for KeyMedia integration
 *
 * @author Raymond Julin <raymond@keyteq.no>
 * @since 1.0.0
 */
class KeyMedia extends \ezote\lib\Controller
{

    const LAYOUT = 'pagelayout.tpl';

    /**
     * Renders the KeyMedia dashboard
     *
     * @return \ezote\lib\Response
     */
    public function dashboard()
    {
        return self::response(
            array('backends' => $this->backends()),
            array(
                'template' => 'design:dashboard/dashboard.tpl',
                'left_menu' => 'design:dashboard/left_menu.tpl',
                'pagelayout' => static::LAYOUT
            )
        );
    }

    public function connection($id = null)
    {
        // Edit existing
        if ($id)
        {
            $backend = Backend::first(compact('id'));
        }

        if ($this->http->method('post'))
        {
            if (!isset($backend)) $backend = Backend::create();

            $ezhttp = $this->http->ez();

            $username = $ezhttp->variable('username', false);
            $host = $ezhttp->variable('host', false);
            $api_key = $ezhttp->variable('api_key', false);
            $api_version = $ezhttp->variable('api_version', false);

            $data = compact('id', 'username', 'host', 'api_key', 'api_version');

            $this->save($backend, $data);

            if ($redirectTo = $ezhttp->variable('redirect_to', false))
            {
                $id = $backend->attribute('id');
                header("Location: {$redirectTo}/{$id}");
            }
        }

        $backends = $this->backends();
        return self::response(
            compact('backend', 'backends'),
            array(
                'template' => 'design:dashboard/connection.tpl',
                'left_menu' => 'design:dashboard/left_menu.tpl',
                'pagelayout' => static::LAYOUT
            )
        );
    }

    /**
     * eZJSCore method for providing scaler GUI tools
     * @param array $args
     */
    public static function scaler($args)
    {
        return self::_templates(array(
            'skeleton' => 'design:parts/scaler.tpl',
            'scale' => 'design:parts/scale_version.tpl'
        ));
    }

    /**
     * eZJSCore method for saving a new scaled version
     */
    public static function saveVersion(array $args = array())
    {
        list($attributeId, $version) = $args;
        if ($attributeId && $version)
        {
            $http = \eZHTTPTool::instance();

            $transformations = array(
                'coords' => $http->variable('coords'),
                'size' => $http->variable('size')
            );

            $name = $http->variable('name');

            // Store information on content object
            $attribute = eZContentObjectAttribute::fetch($attributeId, $version);

            $isKeymediaAttribute = ($attribute->attribute('data_type_string') == 'keymedia' ? true : false);

            $versionObject = \eZContentObjectVersion::fetchVersion($attribute->attribute('version'), $attribute->attribute('contentobject_id'));

            if ($isKeymediaAttribute)
            {
                /**
                 * Update version modified
                 */
                /** @var $versionObject \eZContentObjectVersion */
                if ($versionObject)
                {
                    $versionObject->setAttribute('modified', time());
                    if ($versionObject->attribute('status') == \eZContentObjectVersion::STATUS_INTERNAL_DRAFT)
                        $versionObject->setAttribute('status', \eZContentObjectVersion::STATUS_DRAFT);
                    $versionObject->store();
                }

                // @var \keymedia\models\media\Handler
                $handler = $attribute->content();
                $data = $handler->addVersion($name, $transformations);
            }
            else
            {
                $handler = new Handler();
                $filename = $handler->mediaName($attribute, $versionObject, false, $name);

                // Push to backend
                $keymediaId = $http->variable('keymediaId');
                $mediaId = $http->variable('mediaId');

                $backend = Backend::first(array('id' => $keymediaId));
                $resp = $backend->addVersion($mediaId, $filename, $transformations);

                if (isset($resp->error))
                    $data = array('ok' => false, 'error' => $resp->error);
                else {
                    $url = $resp->url;
                    $data = compact('name', 'url') + $transformations;
                }
            }
        }
        return $data;
    }

    /**
     * eZJSCore method for browsing KeyMedia
     */
    public static function browse(array $args = array())
    {
        $criteria = array();
        list($attributeId, $version) = $args;
        if ($attributeId && $version)
        {
            $attribute = eZContentObjectAttribute::fetch($attributeId, $version);
            $isKeymediaAttribute = ($attribute->attribute('data_type_string') == 'keymedia' ? true : false);

            if ($isKeymediaAttribute)
            {
                $handler = $attribute->content();
                $box = $handler->attribute('minSize');
                $criteria['minWidth'] = $box->width();
                $criteria['minHeight'] = $box->height();
                $backend = $handler->attribute('backend');
            }
            else
            {
                /**
                 * If ezxmltext attribute is specified, use the first DAM
                 */
                $ini = \eZINI::instance('keymedia.ini');
                if ($ini->hasVariable('KeyMedia', 'DefaultBackend')) {
                    $id = $ini->variable('KeyMedia', 'DefaultBackend');
                    $backend = Backend::first(compact('id'));
                }
                else {
                    $backends = self::backends();
                    if (count($backends))
                        $backend = $backends[0];
                    else
                        return array('error' => 'No DAM is configured');
                }
            }
        }
        $http = \eZHTTPTool::instance();
        $q = $http->variable('q', '');
        $width = 160;
        $height = 120;
        $offset = $http->variable('offset', 0);
        $limit = $http->variable('limit', 25);

        $results = $backend->search($q, $criteria, compact('width', 'height', 'offset', 'limit'));

        $keymediaId = $backend->id;
        $data = compact('results', 'keymediaId');
        return $data;
    }

    /**
     * Upload an media from disk
     */
    public static function upload()
    {
        $http = eZHTTPTool::instance();
        $httpFile = eZHTTPFile::fetch('file');

        $attributeId = $http->postVariable('AttributeID');
        $version = $http->postVariable('ContentObjectVersion');

        $attribute = eZContentObjectAttribute::fetch($attributeId, $version);
        $isKeymediaAttribute = ($attribute->attribute('data_type_string') == 'keymedia' ? true : false);

        if ($isKeymediaAttribute)
        {
            $handler = $attribute->content();
            if (!$media = $handler->uploadFile($httpFile))
                return array('error' => 'Failed upload');

            $tpl = \eZTemplate::factory();
            $tpl->setVariable('attribute', $attribute);
            $tpl->setVariable('excludeJS', true);
            return array(
                'media' => $media->data(),
                'toScale' == $handler->attribute('toscale'),
                'content' => $tpl->fetch('design:content/datatype/edit/keymedia.tpl'),
                'ok' => true
            );
        }
        else
        {
            /**
             * If ezxmltext attribute is specified, use the first DAM
             */
            $backends = self::backends();
            if (count($backends))
                $backend = $backends[0];
            else
                return array('error' => 'No DAM is configured');

            $filepath = $httpFile->Filename;
            $handler = new Handler();
            $versionObject = \eZContentObjectVersion::fetchVersion($attribute->attribute('version'), $attribute->attribute('contentobject_id'));
            $filename = $handler->mediaName($attribute, $versionObject);

            $media = $backend->upload($filepath, $filename);

            if (!$media)
                return array('error' => 'Failed upload');
            return array(
                'media' => $media->data(),
                'ok' => true
            );
        }
    }

    /**
     * Get media preview
     */
    public static function media(array $args = array())
    {
        list($attributeId, $version) = $args;
        if ($attributeId === 'ezoe')
        {
            /**
             * Use the first DAM
             */
            $backends = self::backends();
            if (count($backends))
            {
                $backend = $backends[0];
                $media = $backend->get($version);
                $media = $media->data();
                $media->keymediaId = $backend->id;

                $keymediaINI = \eZINI::instance('keymedia.ini');
                $aliasList = $keymediaINI->variable('EditorVersion', 'VersionList');
                $toScale = array();
                if (!empty($aliasList) && is_array($aliasList)) {
                    foreach ($aliasList as $name)
                    {
                        if ($size = $keymediaINI->variable($name, 'Size')) {
                            $size = array_map(function($value){ return (int) $value;}, explode('x', $size));
                            if (count($size) != 2 || !is_integer($size[0]) && !is_integer($size[1]))
                                continue;
                            /**
                             * Both dimensions can't be unbound
                             */
                            if ($size[0] == 0 && $size[1] == 0)
                                continue;

                            $toScale[] = array(
                                'name' => $name,
                                'size' => $size
                            );
                        }
                    }
                }
                $classList = $keymediaINI->variable('EditorVersion', 'ClassList');
                $viewModes = $keymediaINI->variable('EditorVersion', 'ViewModes');

                return compact('media', 'toScale', 'classList', 'viewModes');
            }
            else
                return array('error' => 'No DAM is configured');
        }
        else if ($attributeId && $version)
        {
            $attribute = eZContentObjectAttribute::fetch($attributeId, $version);
            $handler = $attribute->content();
            if ($handler && $media = $handler->attribute('media'))
            {
                $toScale = $handler->attribute('toscale');
                $media = $media->data();
            }
            $tpl = \eZTemplate::factory();
            $tpl->setVariable('attribute', $attribute);
            $tpl->setVariable('excludeJS', true);
            $content = $tpl->fetch('design:content/datatype/edit/keymedia.tpl');
            return compact('media', 'content', 'toScale');
        }
    }

    /**
     * eZJSCore method for adding tags to a remote media
     */
    public static function tag(array $args = array())
    {
        list($attributeId, $version) = $args;
        if ($attributeId && $version)
        {
            $attribute = eZContentObjectAttribute::fetch($attributeId, $version);
            $handler = $attribute->content();
            $backend = $handler->attribute('backend');
            $http = \eZHTTPTool::instance();
            $tags = (array) $http->variable('tags');
            $id = $http->variable('id');
            $media = $backend->tag(compact('id'), $tags);
            return $media->data();
        }
        return false;
    }

    /***
     * Render a bunch of templates into an array and return them
     * Defaults to include `skeleton`
     *
     * @param array $templates
     * @return array
     */
    protected static function _templates(array $templates = array())
    {
        $tpl = \eZTemplate::factory();
        $result = array();
        foreach ($templates as $name => $path)
        {
            if ($path)
                $result[$name] = $tpl->fetch($path);
        }

        return $result;
    }

    /**
     * Cache time for retunrned data, only currently used by ezjscPacker
     * Taken from the "interface" `ezjscServerFunctions`
     *
     * @param string $functionName
     * @return int Uniq timestamp (can return -1 to signal that $functionName is not cacheable)
     */
    public static function getCacheTime($functionName)
    {
        return -1;
    }

    protected function backends()
    {
        return Backend::find();
    }

    /**
     * Add new mediabase connection
     */
    protected function save($backend, array $data = array())
    {
        $keys = array('id', 'host', 'username', 'api_key', 'api_version');

        foreach ($keys as $key)
        {
            if (isset($data[$key]))
                $backend->setAttribute($key, $data[$key]);
        }

        return $backend->store();
    }

}
