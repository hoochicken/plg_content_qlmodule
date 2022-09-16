<?php
/**
 * @package        plg_content_qlmodule
 * @copyright    Copyright (C) 2022 ql.de All rights reserved.
 * @author        Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

//no direct access
defined('_JEXEC') or die ('Restricted Access');

jimport('joomla.plugin.plugin');

class plgContentQlmodule extends JPlugin
{

    protected $start = 'qlmodule';
    protected $arr_attributes = array('qlmoduleId',);
    protected $attributes = [];
    protected $matches = [];

    /**
     * onContentPrepare :: some kind of controller of plugin
     */
    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context == 'com_finder.indexer') return true;
        if (false === strpos($article->text, '{' . $this->start)) return true;
        $article->text = $this->getContent($article->text);
    }

    /**
     *
     */
    function getHtml($arr)
    {
        $html = [];
        if (!isset($arr['qlmoduleId'])) return '';
        $obj_module = self::getModule($arr['qlmoduleId']);
        if (false != $obj_module && 1 == self::checkPublished($obj_module)) {
            if ('mod_qlmodule' != $obj_module->module) {
                $html[] = $this->renderModule($obj_module, $arr);
            }
        }
        //print_R($html);die;
        return implode('', $html);
    }

    /**
     * @param $str
     * @return array
     */
    function getContent($str)
    {
        $regex = '!{' . $this->start . '(.*?)/}!s';
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        $arr_content = [];

        if (0 === count($matches)) return $str;

        foreach ($matches as $k => $v) {
            $arr_content[$k] = [];
            $arr_content[$k] = array_merge($arr_content[$k], $this->getAttributes($v[1]));
            $html = $this->getHtml($this->getAttributes($v[1]));;
            $str = str_replace($v[0] . '</p>', $html, $str);
            $str = str_replace($v[0], $html, $str);
            unset($html);
        }
        return $str;
    }

    /*
     * method to get attributes
     */
    function getAttributes($str)
    {
        $str = strip_tags($str);
        $attributes = [];
        $attributes['qlmoduleId'] = '';

        //$selector=implode('|',$this->arr_attributes);
        //$regex='~('.$selector.')="(.*?)"~';
        $regex = '~(.*?)="(.*?)"~';
        preg_match_all($regex, $str, $matches);
        //echo '<pre>'; echo $str;print_r($matches);die;
        foreach ($matches[0] as $k => $v) {
            if ('' != $matches[2][$k]) {
                $newKey = trim($matches[1][$k]);
                $newValue = $matches[2][$k];
                if (false !== strpos($newValue, 'JSON')) {
                    $newValue = substr($newValue, 4);
                    $newValue = str_replace('\'', '"', $newValue);
                    $newValue = json_decode($newValue);
                }
                $newValue = str_replace('~~', "\n", $newValue);
                $attributes[$newKey] = $newValue;
            }
        }
        //echo '<pre>'; print_r($attributes);die;
        return $attributes;
    }

    /*
    * method to get attributes
    */
    function replaceTags($text)
    {
        if (count($this->matches) > 0) {
            foreach ($this->matches as $k => $match) {
                $arrAttributes = $this->getAttributes($match[1]);
                $output = '';
                $obj_module = self::getModule($arrAttributes['id']);
                if (false != $obj_module && 1 == self::checkPublished($obj_module)) {
                    if ('mod_qlmodule' != $obj_module->module) {
                        if (isset($this->arr_params[$k])) $output .= $this->renderModule($obj_module, $this->arr_params[$k]);
                        else $output .= $this->renderModule($obj_module);
                    }
                }
                $text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $text, 1);
            }
        }
        return $text;
    }

    /**
     * method to get module object from database
     * @param integer module id
     * @return object module data
     */
    public function getModule($moduleId)
    {
        $selector = '*';
        $table = '#__modules';
        if (is_numeric($moduleId)) $where = '`id`=\'' . $moduleId . '\'';
        else {
            JFactory::getApplication()->enqueueMessage(sprintf(JText::_('PLG_CONTENT_NOTPROPERID'), $moduleId) . '<br />' . JText::_('PLG_CONTENT_IDMUSTINTEGER'));
            return false;
        }
        $obj_module = self::askDb($selector, $table, $where);
        if (false == $obj_module) {
            JFactory::getApplication()->enqueueMessage(sprintf(JText::_('PLG_CONTENT_IDNOTFOUND'), $moduleId));
            return false;
        } else return $obj_module;
    }

    /**
     * method to render module, means get html output
     */
    function renderModule($obj_module, $params = [])
    {
        $renderer = JFactory::getDocument()->loadRenderer('module');
        $params = json_encode(array_merge((array)json_decode($obj_module->params), $params));
        $obj_module->params = $params;
        //echo "<pre>";print_r($obj_module->params);die;
        ob_start();
        echo $renderer->render($obj_module);
        $output = ob_get_clean();
        return $output;
    }

    /**
     * method to check if module allows output
     * @param object module
     */
    function checkQlmoduleVersion($module)
    {
        $selector = '*';
        $table = '#__extensions';
        $where = '`name`=\'' . $module->module . '\'';
        $result = self::askDb($selector, $table, $where);
        $manifest_cache = json_decode($result->manifest_cache);
        if ($manifest_cache->version >= 7) return true; else return false;
    }

    /**
     * method to check if module is published
     * @param object module
     * @return bool true on published, false on not
     */
    function checkPublished($module)
    {
        if (1 != $module->published) return false;
        $date = date('Y-m-d H:i:s');
        if
        (
            ('0000-00-00 00:00:00' == $module->publish_up && '0000-00-00 00:00:00' == $module->publish_down)
            ||
            ('0000-00-00 00:00:00' == $module->publish_up && $date < $module->publish_down)
            ||
            ($date > $module->publish_up && '0000-00-00 00:00:00' == $module->publish_down)
            ||
            ($date > $module->publish_up && $date < $module->publish_down)
        ) return true;
        else return false;
    }

    /**
     * method to ask database
     * @param string selector
     * @param string table of database
     * @param string where clause for query
     * @return object with data
     */
    function askDb($selector, $table, $where)
    {
        $db = JFactory::getDbo();
        $db->setQuery('SELECT ' . $selector . ' FROM `' . $table . '` WHERE ' . $where . '');
        return $db->loadObject();
    }

    private function addStyles()
    {
        $styles = [];
        JFactory::getDocument()->addStyleDeclaration(implode("\n", $styles));
    }
}