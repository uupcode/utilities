<?php

declare(strict_types=1);

namespace UupCode\Utilities\Http;

/**
 * Base request for AJAX handlers — adds nonce contract on top of BaseRequest.
 *
 * Extend this class per handler to define nonce + authorization:
 *
 *   class DeleteItemRequest extends AjaxRequest
 *   {
 *       public function authorize(): bool    { return current_user_can('delete_posts'); }
 *       public function nonceAction(): string { return 'delete_item'; }
 *   }
 */
class AjaxRequest extends BaseRequest
{
    /**
     * The nonce action to verify before this request is handled.
     * Return an empty string to skip nonce verification.
     */
    public function nonceAction(): string
    {
        return '';
    }

    /**
     * The request field that holds the nonce value.
     */
    public function nonceField(): string
    {
        return '_wpnonce';
    }
}
