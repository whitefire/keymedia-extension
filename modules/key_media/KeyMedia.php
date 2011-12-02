<?php
/**
 * KeyMedia eZ extension
 *
 * @copyright     Copyright 2011, Keyteq AS (http://keyteq.no/labs)
 */

namespace ezr_keymedia\modules\key_media;

use \eZPersistentObject;
use \ezr_keymedia\models\Backend;

/**
 * Interact with data structures (content objects)
 *
 * @author Bjørnar Helland <bh@keyteq.no>
 * @author Raymond Julin <raymond@keyteq.no>
 * @since 24.10.2011
 *
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
        $data = array();
        $data['backends'] = eZPersistentObject::fetchObjectList(Backend::definition());
        return self::response(
            $data,
            array(
                'template' => 'design:dashboard/dashboard.tpl',
                'pagelayout' => static::LAYOUT
            )
        );
    }

    /**
     * Add new mediabase connection
     */
    public function addConnection()
    {
        $data = array();
        if ($this->http->method('post'))
        {
            $ezhttp = $this->http->ez();
            $username = $ezhttp->variable('username', false);
            $host = $ezhttp->variable('host', false);
            $api_key = $ezhttp->variable('api_key', false);
            $redirectTo = $ezhttp->variable('redirect_to', false);

            $data = compact('username', 'host', 'api_key');
            $backend = Backend::create($data);
            $backend->store();
            if ($redirectTo)
            {
                $id = $backend->id;
                header("Location: {$redirectTo}?added={$id}");
            }
        }

        return self::response(
            $data,
            array(
                'template' => 'design:dashboard/add_connection.tpl',
                'pagelayout' => static::LAYOUT
            )
        );
    }

    /**
     * eZJSCore method for browsing KeyMedia
     */
    public static function browse($args)
    {
        $http = \eZHTTPTool::instance();
        if ($id = array_pop($args))
        {
            $q = $http->variable('q', '');
            $width = 160;
            $height = 120;
            $offset = 0;
            $limit = 25;
            $id = (int) $id;

            $tpl = \eZTemplate::factory();
            $backend = Backend::first(compact('id'));
            $results = $backend->search($q, array(), compact('width', 'height', 'offset', 'limit'));

            if ($http->variable('skeleton', false))
                $skeleton = $tpl->fetch('design:content/keymedia/browse.tpl');

            if ($http->variable('modal', false))
                $modal = $tpl->fetch('design:parts/modal.tpl');

            $item = $tpl->fetch('design:parts/keymedia_browser_item.tpl');

            $data = compact('results', 'skeleton', 'modal', 'item');
        }
        return $data;
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

}