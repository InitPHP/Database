<?php
/**
 * Utils/Pagination
 *
 * This file is part of InitPHP Database.
 *
 * @author      Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright   Copyright © 2022 Muhammet ŞAFAK
 * @license     ./LICENSE  MIT
 * @version     2.0.6
 * @link        https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Database\Utils;

use InitPHP\Database\Result;

/**
 * @mixin Result
 */
final class Pagination
{

    private Result $result;

    protected int $page = 1;
    protected int $perPageLimit = 10;
    protected int $totalPage = 0;
    protected int $totalRow = 0;
    protected int $howDisplayedPage = 8;
    protected string $linkTemplate = '';

    public function __construct(Result &$res, int $page, int $perPageLimit, int $totalRow, string $linkTemplate)
    {
        $this->result = &$res;

        $this->page = $page;
        $this->perPageLimit = $perPageLimit;
        $this->totalRow = $totalRow;
        $this->linkTemplate = $linkTemplate;

        $this->totalPage = \ceil(($this->totalRow / $this->perPageLimit));
    }

    public function __call($name, $arguments)
    {
        return $this->getResult()->{$name}(...$arguments);
    }

    public function getResult(): Result
    {
        return $this->result;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getTotalPage(): int
    {
        return $this->totalPage;
    }

    public function getTotalRow(): int
    {
        return $this->totalRow;
    }

    public function setDisplayedPage(int $displayedPage): self
    {
        $this->howDisplayedPage = ($displayedPage % 2 !== 0) ? $displayedPage + 1 : $displayedPage;

        return $this;
    }

    public function getDisplayedPage(): int
    {
        return $this->howDisplayedPage;
    }

    public function setBaseLink($link): self
    {
        $this->linkTemplate = $link;

        return $this;
    }

    public function getPages(): array
    {
        $pages = [];
        $beforeAfter = $this->howDisplayedPage / 2;
        $beforeLimit = ($beforeAfter > $this->page) ? ($beforeAfter - ($beforeAfter - $this->page)) : $beforeAfter;
        $afterLimit = $this->howDisplayedPage - $beforeLimit;

        for ($i = ($this->page - 1); $i >= ($this->page - $beforeLimit); --$i) {
            $pages[] = [
                'url'       => $this->_linkGenerator($i),
                'page'      => $i,
                'active'    => false,
            ];
        }
        $pages = \array_reverse($pages, false);

        $pages[] = [
            'url'       => $this->_linkGenerator($this->page),
            'page'      => $this->page,
            'active'    => true,
        ];

        for ($i = ($this->page + 1); $i <= ($this->page + $afterLimit); ++$i) {
            if($i > $this->totalPage){
                break;
            }
            $pages[] = [
                'url'       => $this->_linkGenerator($i),
                'page'      => $i,
                'active'    => false,
            ];
        }

        return $pages;
    }

    public function nextPage(): ?array
    {
        if($this->page < $this->totalPage && $this->totalPage > 1){
            $page = $this->page + 1;
            return [
                'url'       => $this->_linkGenerator($page),
                'page'      => $page,
            ];
        }
        return null;
    }

    public function prevPage(): ?array
    {
        if($this->page > 1){
            $page = $this->page - 1;
            return [
                'url'       => $this->_linkGenerator($page),
                'page'      => $page,
            ];
        }
        return null;
    }

    public function toHTML(array $attrs = []): string
    {
        $pages = $this->getPages();
        $prev = $this->prevPage();
        $next = $this->nextPage();
        $li_attr_prepare = '';
        $li_a_attr_prepare = '';
        $li_attr_prepare_active = ' class="active"';
        if(isset($attrs['li'])){
            $li_attr_prepare = $this->_attrGenerator($attrs['li']);
            $li_a_attr_prepare = $this->_attrGenerator(($attrs['li']['a'] ?? []));
            if(isset($attrs['li']['class'])){
                $attrs['li']['class'] .= ' active';
            }else{
                $attrs['li']['class'] = 'active';
            }
            $li_attr_prepare_active = $this->_attrGenerator($attrs['li']);
        }

        $html = '<nav'
            . $this->_attrGenerator(($attrs['nav'] ?? []))
            . '><ul'
            . $this->_attrGenerator(($attrs['ul'] ?? []))
            . '>';
        if($prev !== null){
            $html .= '<li'
                . $li_attr_prepare
                . '><a href="' . $prev['url'] . '"'
                . $li_a_attr_prepare
                . '>' . ($attrs['prev_label'] ?? '&laquo;') . '</a></li>';
        }

        foreach ($pages as $page) {
            $html .= '<li'
                . ($page['active'] ? $li_attr_prepare_active : $li_attr_prepare)
                . '><a href="' . $page['url'] . '"'
                . $li_a_attr_prepare
                . '>'
                . $page['page']
                . '</a></li>';
        }

        if($next !== null){
            $html .= '<li'
                . $li_attr_prepare
                . '><a href="' . $next['url'] . '"'
                . $li_a_attr_prepare
                . '>' . ($attrs['next_label'] ?? '&raquo;') . '</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }


    private function _attrGenerator(array $assoc): string
    {
        if($assoc === []){
            return '';
        }
        $res = '';
        foreach ($assoc as $name => $value) {
            if(\is_array($value)){
                continue;
            }
            $res .= ' ' . $name . '="' . $value . '"';
        }
        return $res;
    }


    private function _linkGenerator($page)
    {
        if(empty($this->linkTemplate)){
            return $page;
        }
        return \strtr($this->linkTemplate, [
            '{page}'        => $page,
        ]);
    }

}
