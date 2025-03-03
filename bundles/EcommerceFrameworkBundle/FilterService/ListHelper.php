<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\FilterService;

use Pimcore\Bundle\EcommerceFrameworkBundle\IndexService\ProductList\ProductListInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractCategory;
use Pimcore\Model\DataObject\Fieldcollection\Data\OrderByFields;

/**
 * Helper service class for setting up a product list utilizing the filter service
 * based on a filter definition and set filter parameters
 */
class ListHelper
{
    /**
     * @param \Pimcore\Model\DataObject\FilterDefinition $filterDefinition
     * @param ProductListInterface $productList
     * @param array $params
     * @param FilterService $filterService
     * @param bool $loadFullPage
     * @param bool $excludeLimitOfFirstpage
     */
    public function setupProductList(
        \Pimcore\Model\DataObject\FilterDefinition $filterDefinition,
        ProductListInterface $productList,
        &$params,
        FilterService $filterService,
        $loadFullPage,
        $excludeLimitOfFirstpage = false
    ) {
        $orderByOptions = [];
        $orderKeysAsc = explode(',', $filterDefinition->getOrderByAsc());
        if (!empty($orderKeysAsc)) {
            foreach ($orderKeysAsc as $orderByEntry) {
                if (!empty($orderByEntry)) {
                    $orderByOptions[$orderByEntry]['asc'] = true;
                }
            }
        }

        $orderKeysDesc = explode(',', $filterDefinition->getOrderByDesc());
        if (!empty($orderKeysDesc)) {
            foreach ($orderKeysDesc as $orderByEntry) {
                if (!empty($orderByEntry)) {
                    $orderByOptions[$orderByEntry]['desc'] = true;
                }
            }
        }

        $offset = 0;

        $pageLimit = isset($params['perPage']) ? (int)$params['perPage'] : null;
        if (!$pageLimit) {
            $pageLimit = $filterDefinition->getPageLimit();
        }
        if (!$pageLimit) {
            $pageLimit = 50;
        }
        $limitOnFirstLoad = $filterDefinition->getLimitOnFirstLoad();
        if (!$limitOnFirstLoad) {
            $limitOnFirstLoad = 6;
        }

        if (isset($params['page'])) {
            $params['currentPage'] = (int)$params['page'];
            $offset = $pageLimit * ($params['page'] - 1);
        }
        if ($filterDefinition->getAjaxReload()) {
            if ($loadFullPage && !$excludeLimitOfFirstpage) {
                $productList->setLimit($pageLimit);
            } elseif ($loadFullPage && $excludeLimitOfFirstpage) {
                $offset += $limitOnFirstLoad;
                $productList->setLimit($pageLimit - $limitOnFirstLoad);
            } else {
                $productList->setLimit($limitOnFirstLoad);
            }
        } else {
            $productList->setLimit($pageLimit);
        }
        $productList->setOffset($offset);

        $params['pageLimit'] = $pageLimit;

        $orderByField = null;
        $orderByDirection = null;

        if (isset($params['orderBy'])) {
            $orderBy = explode('#', $params['orderBy']);
            $orderByField = $orderBy[0];

            if (count($orderBy) > 1) {
                $orderByDirection = $orderBy[1];
            }
        }

        if (array_key_exists($orderByField, $orderByOptions)) {
            $params['currentOrderBy'] = htmlentities($params['orderBy']);

            $productList->setOrderKey($orderByField);

            if ($orderByDirection) {
                $productList->setOrder($orderByDirection);
            }
        } else {
            $orderByCollection = $filterDefinition->getDefaultOrderBy();
            $orderByList = [];
            if ($orderByCollection) {
                /** @var OrderByFields $orderBy */
                foreach ($orderByCollection as $orderBy) {
                    if ($orderBy->getField()) {
                        $orderByList[] = [$orderBy->getField(), $orderBy->getDirection()];
                    }
                }

                $params['currentOrderBy'] = implode('#', reset($orderByList));
            }
            if ($orderByList) {
                $productList->setOrderKey($orderByList);
                $productList->setOrder('ASC');
            }
        }

        if ($filterService) {
            $params['currentFilter'] = $filterService->initFilterService($filterDefinition, $productList, $params);
        }

        $params['orderByOptions'] = $orderByOptions;
    }

    /**
     * @param int $page
     *
     * @return string
     */
    public function createPagingQuerystring($page)
    {
        $params = $_REQUEST;
        $params['page'] = $page;
        unset($params['fullpage']);

        $string = '?';
        foreach ($params as $k => $p) {
            if (is_array($p)) {
                foreach ($p as $subKey => $subValue) {
                    $string .= $k . '[' . $subKey . ']' . '=' . urlencode($subValue) . '&';
                }
            } else {
                $string .= $k . '=' . urlencode($p) . '&';
            }
        }

        return $string;
    }

    /**
     * @param array $conditions
     *
     * @return AbstractCategory|null
     */
    public function getFirstFilteredCategory($conditions)
    {
        if (!empty($conditions)) {
            foreach ($conditions as $c) {
                if ($c instanceof \Pimcore\Model\DataObject\Fieldcollection\Data\FilterCategory) {
                    $result = $c->getPreSelect();
                    if ($result instanceof AbstractCategory) {
                        return $result;
                    }
                }
            }
        }

        return null;
    }
}
