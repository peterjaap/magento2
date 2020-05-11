<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\WishlistGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Wishlist\Model\ResourceModel\Wishlist as WishlistResourceModel;
use Magento\Wishlist\Model\Wishlist\AddProductsToWishlist;
use Magento\Wishlist\Model\Wishlist\Config as WishlistConfig;
use Magento\Wishlist\Model\Wishlist\Data\Error;
use Magento\Wishlist\Model\Wishlist\Data\WishlistItemFactory;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\WishlistGraphQl\Mapper\WishlistDataMapper;

/**
 * Adding products to wishlist resolver
 */
class AddProductsToWishlistResolver implements ResolverInterface
{
    /**
     * @var AddProductsToWishlist
     */
    private $addProductsToWishlist;

    /**
     * @var WishlistDataMapper
     */
    private $wishlistDataMapper;

    /**
     * @var WishlistConfig
     */
    private $wishlistConfig;

    /**
     * @var WishlistResourceModel
     */
    private $wishlistResource;

    /**
     * @var WishlistFactory
     */
    private $wishlistFactory;

    /**
     * @param WishlistResourceModel $wishlistResource
     * @param WishlistFactory $wishlistFactory
     * @param WishlistConfig $wishlistConfig
     * @param AddProductsToWishlist $addProductsToWishlist
     * @param WishlistDataMapper $wishlistDataMapper
     */
    public function __construct(
        WishlistResourceModel $wishlistResource,
        WishlistFactory $wishlistFactory,
        WishlistConfig $wishlistConfig,
        AddProductsToWishlist $addProductsToWishlist,
        WishlistDataMapper $wishlistDataMapper
    ) {
        $this->wishlistResource = $wishlistResource;
        $this->wishlistFactory = $wishlistFactory;
        $this->wishlistConfig = $wishlistConfig;
        $this->addProductsToWishlist = $addProductsToWishlist;
        $this->wishlistDataMapper = $wishlistDataMapper;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$this->wishlistConfig->isEnabled()) {
            throw new GraphQlInputException(__('The wishlist is not currently available.'));
        }

        $customerId = $context->getUserId();

        /* Guest checking */
        if (!$customerId && 0 === $customerId) {
            throw new GraphQlAuthorizationException(__('The current user cannot perform operations on wishlist'));
        }

        $wishlistId = $args['wishlist_id'] ?: null;
        $wishlistItemsData = $args['wishlist_items'];
        $wishlist = $this->wishlistFactory->create();

        if ($wishlistId) {
            $this->wishlistResource->load($wishlist, $wishlistId);
        } elseif ($customerId) {
            $wishlist->loadByCustomerId($customerId, true);
        }

        if (null === $wishlist->getId()) {
            throw new GraphQlInputException(__('Something went wrong while creating the wishlist'));
        }

        $wishlistItems = [];
        foreach ($wishlistItemsData as $wishlistItemData) {
            $wishlistItems[] = (new WishlistItemFactory())->create($wishlistItemData);
        }

        $wishlistOutput = $this->addProductsToWishlist->execute($wishlist, $wishlistItems);

        return [
            'wishlist' => $this->wishlistDataMapper->map($wishlistOutput->getWishlist()),
            'userInputErrors' => \array_map(
                function (Error $error) {
                    return [
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                    ];
                },
                $wishlistOutput->getErrors()
            )
        ];
    }
}
