<?php
/**
 * @package        plg_content_qlmodule
 * @copyright    Copyright (C) 2023 ql.de All rights reserved.
 * @author        Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

//no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

defined('_JEXEC') or die ('Restricted Access');

jimport('joomla.plugin.plugin');

class plgContentQlmodule extends JPlugin
{

    protected string $start = 'qlmodule';
    protected array $attributes = [];
    protected array $matches = [];

    function __construct(&$subject, $config) {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_content_qlmodule', dirname(__FILE__));
        parent::__construct($subject, $config);
    }

    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ($context == 'com_finder.indexer') return true;
        if (false === strpos($article->text, '{' . $this->start)) return true;
        $article->text = $this->getContent($article->text);
    }

    /**
     *
     */
    private function getHtml(array $arr)
    {
        if (!isset($arr['qlmoduleId'])) return '';

        $module = self::getModule($arr['qlmoduleId']);
        $html = [];
        if (false != $module && 1 == self::checkPublished($module) && 'mod_qlmodule' != $module->module) {
            $html[] = $this->renderModule($module, $arr);
        }
        //print_R($html);die;
        return implode('', $html);
    }

    private function getContent($str)
    {
        // $regex = '!{' . $this->start . '(.*?)/}!s';
        $regex = '!{' . $this->start . '(.*?)([\/]{0,1})}!s';
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

    private function getAttributes(string $str): array
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

    private function replaceTags(string $text): string
    {
        if (count($this->matches) === 0) return $text;

        foreach ($this->matches as $k => $match) {
            $arrAttributes = $this->getAttributes($match[1]);
            $output = '';
            $module = self::getModule($arrAttributes['id']);
            if (false != $module && 1 == self::checkPublished($module)) {
                if ('mod_qlmodule' != $module->module) {
                    if (isset($this->arr_params[$k])) $output .= $this->renderModule($module, $this->arr_params[$k]);
                    else $output .= $this->renderModule($module);
                }
            }
            $text = preg_replace("|$match[0]|", addcslashes($output, '\\$'), $text, 1);
        }
        return $text;
    }

    public function getModule(int $moduleId)
    {
        $selector = '*';
        $table = '#__modules';
        if (is_numeric($moduleId)) $where = '`id`=\'' . $moduleId . '\'';
        else {
            JFactory::getApplication()->enqueueMessage(sprintf(Text::_('PLG_CONTENT_QLMODULE_NOTPROPERID'), $moduleId) . '<br />' . JText::_('PLG_CONTENT_QLMODULE_IDMUSTINTEGER'));
            return false;
        }
        $module = self::askDb($selector, $table, $where);
        if (!$module) {
            JFactory::getApplication()->enqueueMessage(sprintf(Text::_('PLG_CONTENT_QLMODULE_IDNOTFOUND'), $moduleId));
            return false;
        } else return $module;
    }

    private function renderModule(stdClass $module, array $params = []): string
    {
        $renderer = JFactory::getDocument()->loadRenderer('module');
        $params = json_encode(array_merge((array)json_decode($module->params), $params));
        $module->params = $params;
        //echo "<pre>";print_r($module->params);die;
        ob_start();
        echo $renderer->render($module);
        return ob_get_clean();
    }

    private function checkQlmoduleVersion(stdClass $module): bool
    {
        $selector = '*';
        $table = '#__extensions';
        $where = '`name`=\'' . $module->module . '\'';
        $result = self::askDb($selector, $table, $where);
        $manifest_cache = json_decode($result->manifest_cache);
        return $manifest_cache->version >= 7;
    }

    private function checkPublished(stdClass $module)
    {
        if (1 !== $module->published) return false;
        $date = date('Y-m-d H:i:s');
        if
        (
            ('0000-00-00 00:00:00' == $module->publish_up && '0000-00-00 00:00:00' == $module->publish_down)
            ||
            ('0000-00-00 00:00:00' == $module->publish_up && $date < $module->publish_down)
            ||
            (is_null($module->publish_up) && is_null($module->publish_down))
            ||
            (is_null($module->publish_up) && $date < $module->publish_down)
            ||
            ($date > $module->publish_up && is_null($module->publish_down))
            ||
            ($date > $module->publish_up && '0000-00-00 00:00:00' == $module->publish_down)
            ||
            ($date > $module->publish_up && $date < $module->publish_down)
        ) return true;
        else return false;
    }

    private function askDb($selector, $table, $where)
    {
        $db = JFactory::getDbo();
        $db->setQuery(sprintf('SELECT %s FROM `%s` WHERE %s', $selector, $table, $where));
        return $db->loadObject();
    }

    private function addStyles()
    {
        $styles = [];
        JFactory::getDocument()->addStyleDeclaration(implode("\n", $styles));
    }
}
