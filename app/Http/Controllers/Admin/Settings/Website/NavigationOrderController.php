<?php

namespace App\Http\Controllers\Admin\Settings\Website;

use App\Models\SportType;
use App\Models\NavigationWebsite;
use Illuminate\Http\Request;

use App\Http\Requests;
use Titan\Controllers\TitanAdminController;

class NavigationOrderController extends TitanAdminController
{
    private $navigationType = 'main';

    private $orderProperty = 'list_main_order';

    private function updateNavType($type = 'main')
    {
        $this->navigationType = $type;
        $this->orderProperty = 'list_' . $type . '_order';
    }

    /**
     * Display a listing of the resource.
     *
     * @param string $type
     * @return Response
     */
    public function index($type = 'main')
    {
        $this->updateNavType($type);

        $itemsHtml = $this->getNavigationHtml();

        return $this->view('settings.website.navigations.order', compact('itemsHtml'));
    }

    /**
     * Update the order of navigation
     *
     * @param Request $request
     * @return array
     */
    public function updateOrder(Request $request)
    {
        $type = 'main'; // tmp for now
        $this->updateNavType($type);

        $navigation = json_decode($request->get('list'), true);

        foreach ($navigation as $key => $nav) {
            $row = $this->updateNavigationListOrder($nav['id'], ($key + 1));

            $this->updateIfNavHasChildren($nav);
        }

        return ['result' => 'success'];
    }

    /**
     * Generate the nestable html
     *
     * @param null $parent
     *
     * @return string
     */
    private function getNavigationHtml($parent = null)
    {
        $html = '<ol class="dd-list">';

        $parentId = ($parent ? $parent->id : 0);
        $items = NavigationWebsite::whereParentIdORM($parentId, $this->navigationType,
            $this->orderProperty);

        foreach ($items as $key => $nav) {
            $html .= '<li class="dd-item" data-id="' . $nav->id . '">';
            $html .= '<div class="dd-handle">' . '<i class="fa-fw fa fa-' . $nav->icon . '"></i> ' . $nav->title . ' <span style="float:right"> ' . $nav->url . ' </span></div>';
            $html .= $this->getNavigationHtml($nav);
            $html .= '</li>';
        }

        $html .= '</ol>';

        return (count($items) >= 1 ? $html : '');
    }

    /**
     * Loop through children and update list order (recursive)
     *
     * @param $nav
     */
    private function updateIfNavHasChildren($nav)
    {
        if (isset($nav['children']) && count($nav['children']) > 0) {
            $children = $nav['children'];
            foreach ($children as $c => $child) {
                $row = $this->updateNavigationListOrder($child['id'], ($c + 1), $nav['id']);

                $this->updateIfNavHasChildren($child);
            }
        }
    }

    /**
     * Update Navigation Item, with new list order and parent id (list and parent can change)
     *
     * @param     $id
     * @param     $listOrder
     * @param int $parentId
     *
     * @return mixed
     */
    private function updateNavigationListOrder($id, $listOrder, $parentId = 0)
    {
        $row = NavigationWebsite::find($id);
        $row->parent_id = $parentId;
        if ($row->url_parent_id != 0) {
            $row->url_parent_id = $parentId; // update the url parent id as well
        }

        $row->updateUrl();
        $row[$this->orderProperty] = $listOrder;
        $row->save();

        return $row;
    }
}