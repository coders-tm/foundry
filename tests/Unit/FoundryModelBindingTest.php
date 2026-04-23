<?php

namespace Foundry\Tests\Unit;

use Foundry\Foundry;
use Foundry\Models\Coupon;
use Foundry\Models\Order;
use Foundry\Models\Subscription;
use Foundry\Models\Subscription\Plan;
use Foundry\Models\SupportTicket;
use Foundry\Tests\BaseTestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Workbench\App\Models\Admin;
use Workbench\App\Models\Coupon as WorkbenchCoupon;
use Workbench\App\Models\Plan as WorkbenchPlan;
use Workbench\App\Models\Subscription as WorkbenchSubscription;
use Workbench\App\Models\User;

class FoundryModelBindingTest extends BaseTestCase
{
    /**
     * Reset static model properties and morph map after each test.
     */
    protected function tearDown(): void
    {
        // Restore defaults
        Foundry::useUserModel('App\\Models\\User');
        Foundry::useAdminModel('App\\Models\\Admin');
        Foundry::useOrderModel(Order::class);
        Foundry::useSubscriptionModel(Subscription::class);
        Foundry::usePlanModel(Plan::class);
        Foundry::useCouponModel(Coupon::class);
        Foundry::useSupportTicketModel(SupportTicket::class);

        parent::tearDown();
    }

    public function test_use_user_model_sets_static_property(): void
    {
        Foundry::useUserModel(User::class);

        $this->assertSame(User::class, Foundry::$userModel);
    }

    public function test_use_user_model_also_sets_subscription_user_model(): void
    {
        Foundry::useUserModel(User::class);

        $this->assertSame(User::class, Foundry::$subscriptionUserModel);
    }

    public function test_use_user_model_registers_morph_map(): void
    {
        Foundry::useUserModel(User::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('User', $morphMap);
        $this->assertSame(User::class, $morphMap['User']);
    }

    public function test_use_user_model_morph_class_matches_map(): void
    {
        Foundry::useUserModel(User::class);

        $this->assertSame('User', (new User)->getMorphClass());
    }

    public function test_use_admin_model_sets_static_property(): void
    {
        Foundry::useAdminModel(Admin::class);

        $this->assertSame(Admin::class, Foundry::$adminModel);
    }

    public function test_use_admin_model_registers_morph_map(): void
    {
        Foundry::useAdminModel(Admin::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('Admin', $morphMap);
        $this->assertSame(Admin::class, $morphMap['Admin']);
    }

    public function test_use_admin_model_morph_class_matches_map(): void
    {
        Foundry::useAdminModel(Admin::class);

        $this->assertSame('Admin', (new Admin)->getMorphClass());
    }

    public function test_use_order_model_sets_static_property(): void
    {
        Foundry::useOrderModel(Order::class);

        $this->assertSame(Order::class, Foundry::$orderModel);
    }

    public function test_use_order_model_registers_morph_map(): void
    {
        Foundry::useOrderModel(Order::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('Order', $morphMap);
        $this->assertSame(Order::class, $morphMap['Order']);
    }

    public function test_use_order_model_morph_class_matches_map(): void
    {
        Foundry::useOrderModel(Order::class);

        $this->assertSame('Order', (new Order)->getMorphClass());
    }

    public function test_use_subscription_model_sets_static_property(): void
    {
        Foundry::useSubscriptionModel(Subscription::class);

        $this->assertSame(Subscription::class, Foundry::$subscriptionModel);
    }

    public function test_use_subscription_model_registers_morph_map(): void
    {
        Foundry::useSubscriptionModel(Subscription::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('Subscription', $morphMap);
        $this->assertSame(Subscription::class, $morphMap['Subscription']);
    }

    public function test_use_subscription_model_morph_class_matches_map(): void
    {
        Foundry::useSubscriptionModel(Subscription::class);

        $this->assertSame('Subscription', (new Subscription)->getMorphClass());
    }

    public function test_custom_subscription_model_morph_class_matches_map(): void
    {
        Foundry::useSubscriptionModel(WorkbenchSubscription::class);

        $this->assertSame('Subscription', (new WorkbenchSubscription)->getMorphClass());
    }

    public function test_use_plan_model_sets_static_property(): void
    {
        Foundry::usePlanModel(Plan::class);

        $this->assertSame(Plan::class, Foundry::$planModel);
    }

    public function test_use_plan_model_registers_morph_map(): void
    {
        Foundry::usePlanModel(Plan::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('Plan', $morphMap);
        $this->assertSame(Plan::class, $morphMap['Plan']);
    }

    public function test_use_plan_model_morph_class_matches_map(): void
    {
        Foundry::usePlanModel(Plan::class);

        $this->assertSame('Plan', (new Plan)->getMorphClass());
    }

    public function test_custom_plan_model_morph_class_matches_map(): void
    {
        Foundry::usePlanModel(WorkbenchPlan::class);

        $this->assertSame('Plan', (new WorkbenchPlan)->getMorphClass());
    }

    public function test_use_coupon_model_sets_static_property(): void
    {
        Foundry::useCouponModel(Coupon::class);

        $this->assertSame(Coupon::class, Foundry::$couponModel);
    }

    public function test_use_coupon_model_registers_morph_map(): void
    {
        Foundry::useCouponModel(Coupon::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('Coupon', $morphMap);
        $this->assertSame(Coupon::class, $morphMap['Coupon']);
    }

    public function test_use_coupon_model_morph_class_matches_map(): void
    {
        Foundry::useCouponModel(Coupon::class);

        $this->assertSame('Coupon', (new Coupon)->getMorphClass());
    }

    public function test_custom_coupon_model_morph_class_matches_map(): void
    {
        Foundry::useCouponModel(WorkbenchCoupon::class);

        $this->assertSame('Coupon', (new WorkbenchCoupon)->getMorphClass());
    }

    public function test_use_support_ticket_model_sets_static_property(): void
    {
        Foundry::useSupportTicketModel(SupportTicket::class);

        $this->assertSame(SupportTicket::class, Foundry::$supportTicketModel);
    }

    public function test_use_support_ticket_model_registers_morph_map(): void
    {
        Foundry::useSupportTicketModel(SupportTicket::class);

        $morphMap = Relation::morphMap();

        $this->assertArrayHasKey('SupportTicket', $morphMap);
        $this->assertSame(SupportTicket::class, $morphMap['SupportTicket']);
    }

    public function test_use_support_ticket_model_morph_class_matches_map(): void
    {
        Foundry::useSupportTicketModel(SupportTicket::class);

        $this->assertSame('SupportTicket', (new SupportTicket)->getMorphClass());
    }

    public function test_use_subscription_user_model_sets_static_property(): void
    {
        Foundry::useSubscriptionUserModel(User::class);

        $this->assertSame(User::class, Foundry::$subscriptionUserModel);
    }

    public function test_use_subscription_user_model_does_not_alter_user_model(): void
    {
        $originalUserModel = Foundry::$userModel;

        Foundry::useSubscriptionUserModel(User::class);

        $this->assertSame($originalUserModel, Foundry::$userModel);
    }

    public function test_all_default_morph_map_keys_resolve_to_expected_classes(): void
    {
        Foundry::useOrderModel(Order::class);
        Foundry::useSubscriptionModel(Subscription::class);
        Foundry::usePlanModel(Plan::class);
        Foundry::useCouponModel(Coupon::class);
        Foundry::useSupportTicketModel(SupportTicket::class);

        $morphMap = Relation::morphMap();

        $this->assertSame(Order::class, $morphMap['Order']);
        $this->assertSame(Subscription::class, $morphMap['Subscription']);
        $this->assertSame(Plan::class, $morphMap['Plan']);
        $this->assertSame(Coupon::class, $morphMap['Coupon']);
        $this->assertSame(SupportTicket::class, $morphMap['SupportTicket']);
    }

    public function test_all_default_models_get_morph_class_equal_to_morph_map_key(): void
    {
        Foundry::useOrderModel(Order::class);
        Foundry::useSubscriptionModel(Subscription::class);
        Foundry::usePlanModel(Plan::class);
        Foundry::useCouponModel(Coupon::class);
        Foundry::useSupportTicketModel(SupportTicket::class);

        $this->assertSame('Order', (new Order)->getMorphClass());
        $this->assertSame('Subscription', (new Subscription)->getMorphClass());
        $this->assertSame('Plan', (new Plan)->getMorphClass());
        $this->assertSame('Coupon', (new Coupon)->getMorphClass());
        $this->assertSame('SupportTicket', (new SupportTicket)->getMorphClass());
    }
}
