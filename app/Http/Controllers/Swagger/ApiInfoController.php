<?php

namespace App\Http\Controllers\Swagger;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Tribe Community API",
 *     description="Auto-generated API documentation for Tribe Community Platform. Includes billing, payments, subscriptions, and user management endpoints."
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter JWT token in format: Bearer {token}"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and registration endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Payments",
 *     description="Payment processing endpoints for invoices"
 * )
 * 
 * @OA\Tag(
 *     name="Billing - Stripe Payments",
 *     description="Stripe payment processing endpoints for invoices and subscriptions"
 * )
 * 
 * @OA\Tag(
 *     name="Billing - Stripe Subscriptions",
 *     description="Stripe subscription management endpoints for creating, updating, and canceling subscriptions"
 * )
 * 
 * @OA\Tag(
 *     name="Billing - Refunds",
 *     description="Refund processing and history endpoints for Stripe payments"
 * )
 * 
 * @OA\Tag(
 *     name="Basecamp Users",
 *     description="API endpoints specifically for basecamp users (individual £10/month subscription users)"
 * )
 */
class ApiInfoController
{
    // This class only holds OpenAPI annotations for L5-Swagger.
}


