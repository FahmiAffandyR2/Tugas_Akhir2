import Vue from 'vue'
import VueRouter from 'vue-router'
import auth from '@/services/AuthService'

Vue.use(VueRouter)

const routes = [
  {
    path: '/staff/dashboard',
    name: 'staff-dashboard',
    component: () => import('@/views/staff/Dashboard.vue'),
    meta: { layout: 'staff', staffOnly: true },
  },
  {
    path: '/',
    name: 'landing',
    component: () => import('@/views/Landing.vue'),
    meta: { layout: 'blank' },
  },
  {
    path: '/customer/login',
    name: 'customer-login',
    redirect: '/login',
  },
  {
    path: '/customer/register',
    name: 'customer-register',
    component: () => import('@/views/customer/Register.vue'),
    meta: { layout: 'blank', customerGuest: true },
  },
  {
    path: '/customer/beranda',
    name: 'customer-home',
    component: () => import('@/views/customer/Home.vue'),
    meta: { layout: 'customer', customerOnly: true },
  },
  {
    path: '/customer/pesan',
    name: 'customer-booking',
    component: () => import('@/views/customer/Booking.vue'),
    meta: { layout: 'customer', customerOnly: true },
  },
  {
    path: '/customer/pemesanan',
    name: 'customer-bookings',
    component: () => import('@/views/customer/Bookings.vue'),
    meta: { layout: 'customer', customerOnly: true },
  },
  {
    path: '/customer/profil',
    name: 'customer-profile',
    component: () => import('@/views/shared/Profile.vue'),
    meta: { layout: 'customer', customerOnly: true },
  },
  {
    path: '/customer/wisata',
    name: 'customer-tourist-stops',
    component: () => import('@/views/customer/TouristStops.vue'),
    meta: { layout: 'customer', customerOnly: true },
  },
  {
    path: '/customer/wisata/:stop_id',
    name: 'customer-stop-detail',
    component: () => import('@/views/customer/StopDetail.vue'),
    meta: { layout: 'customer', customerOnly: true },
  },
  {
    path: '/customer/perjalanan',
    redirect: '/customer/pemesanan',
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('@/views/dashboard/Dashboard.vue'),
  },
  {
    path: '/driver/beranda',
    name: 'driver-home',
    component: () => import('@/views/driver/Home.vue'),
    meta: { layout: 'driver', driverOnly: true },
  },
  {
    path: '/driver/jadwal',
    name: 'driver-schedule',
    component: () => import('@/views/driver/Trips.vue'),
    meta: { layout: 'driver', driverOnly: true, tripFilter: 'schedule' },
  },
  {
    path: '/driver/perjalanan',
    name: 'driver-active-trip',
    component: () => import('@/views/driver/Trips.vue'),
    meta: { layout: 'driver', driverOnly: true, tripFilter: 'active' },
  },
  {
    path: '/driver/riwayat',
    name: 'driver-history',
    component: () => import('@/views/driver/Trips.vue'),
    meta: { layout: 'driver', driverOnly: true, tripFilter: 'history' },
  },
  {
    path: '/driver/profil',
    name: 'driver-profile',
    component: () => import('@/views/shared/Profile.vue'),
    meta: { layout: 'driver', driverOnly: true },
  },
  //////////////////////////users////////////////////////////////
  //admins
  {
    path: '/admin',
    name: 'admin',
    component: () => import('@/views/users/index.vue'),
  },
  //customers
  {
    path: '/customers',
    name: 'customers',
    component: () => import('@/views/users/index.vue'),
  },
  //drivers
  {
    path: '/drivers',
    name: 'drivers',
    component: () => import('@/views/users/index.vue'),
  },
  {
    path: '/users/view/user=:user_id',
    name: 'view-user',
    component: () => import('@/views/users/view-user.vue'),
  },
  {
    path: '/users/edit/user=:user_id',
    name: 'edit-user',
    component: () => import('@/views/users/edit-user.vue'),
  },
    //////////////////////////buses////////////////////////////////
    {
      path: '/buses',
      name: 'buses',
      component: () => import('@/views/system-setup/buses/index.vue'),
    },
    {
      path: '/fleet-depots',
      name: 'fleet-depots',
      component: () => import('@/views/system-setup/fleet-depots/index.vue'),
    },
    {
      path: '/fleet-depots/:depot_id',
      name: 'fleet-depot-detail',
      component: () => import('@/views/system-setup/fleet-depots/show.vue'),
    },
    //////////////////////////buses////////////////////////////////
    {
        path: '/coupons',
        name: 'coupons',
        component: () => import('@/views/coupons/index.vue'),
        },
  //////////////////////////routes////////////////////////////////
  {
    path: '/routes',
    name: 'routes',
    component: () => import('@/views/system-setup/routes/index.vue'),
  },
  {
    path: '/routes/create/route=:route_name',
    name: 'create-route',
    component: () => import('@/views/system-setup/routes/create-edit.vue'),
  },
  {
    path: '/routes/edit/route=:route_id&route_name=:new_route_name',
    name: 'edit-route',
    component: () => import('@/views/system-setup/routes/create-edit.vue'),
  },
  {
    path: '/routes/view/route=:route_id',
    name: 'view-route',
    component: () => import('@/views/system-setup/routes/view.vue'),
  },
  //////////////////////////stops////////////////////////////////
  {
    path: '/stops',
    name: 'stops',
    component: () => import('@/views/system-setup/stops/index.vue'),
  },
  {
    path: '/stops/view/stop=:stop_id',
    name: 'view-stop',
    component: () => import('@/views/system-setup/stops/view.vue'),
  },
  {
    path: '/stops/create',
    name: 'create-stop',
    component: () => import('@/views/system-setup/stops/create-edit.vue'),
  },
  {
    path: '/stops/edit/stop=:stop_id',
    name: 'edit-stop',
    component: () => import('@/views/system-setup/stops/create-edit.vue'),
  },
  {
    path: '/trips',
    name: 'trips',
    component: () => import('@/views/trips/index.vue'),
  },
  //driver-conflicts
  {
    path: '/driver-conflicts',
    name: 'driver-conflicts',
    component: () => import('@/views/trips/driver-conflicts/index.vue'),
  },
  //////////////////////////customers////////////////////////////////
  {
    path: '/trips/create',
    name: 'create-trip',
    component: () => import('@/views/trips/create-edit.vue'),
  },
  {
    path: '/trips/edit/trip=:trip_id&action=:action',
    name: 'edit-trip',
    component: () => import('@/views/trips/create-edit.vue'),
  },
  {
    path: '/trips/view-trip/trip=:trip_id',
    name: 'view-trip',
    component: () => import('@/views/trips/view-trip.vue'),
  },
  {
    path: '/trips/view-calendar/trip=:trip_id&suspension=:suspension_id',
    name: 'view-calendar',
    component: () => import('@/views/trips/calendar/view-calendar.vue'),
  },
  //////////////////////////reservations////////////////////////////////
  {
    path: '/reservations',
    name: 'reservations',
    component: () => import('@/views/reservations/index.vue'),
  },
  { path: '/jadwal-mingguan', name: 'weekly-schedule', component: () => import('@/views/charter-bookings/WeeklySchedule.vue') },
  {
    path: '/charter-bookings',
    name: 'charter-bookings',
    component: () => import('@/views/charter-bookings/index.vue'),
  },
  {
    path: '/complaints',
    name: 'complaints',
    component: () => import('@/views/complaints/index.vue'),
  },
  //////////////////////////planned-trips////////////////////////////////
  {
    path: '/planned-trips',
    name: 'planned-trips',
    component: () => import('@/views/planned-trips/index.vue'),
  },
  //////////////////////////Payments///////////////////////////////////
  {
    path: '/upcoming-payments',
    name: 'upcoming-payments',
    component: () => import('@/views/payments/upcoming-payments/index.vue'),
  },
  {
    path: '/upcoming-payments/view-payment/user=:user_id',
    name: 'view-upcoming-payment',
    component: () => import('@/views/payments/upcoming-payments/view-payment.vue'),
  },
  {
    path: '/redemptions',
    name: 'redemptions',
    component: () => import('@/views/payments/redemptions/index.vue'),
  },
  //////////////////////////live-tracking////////////////////////////////
    {
        path: "/live-tracking",
        name: "live-tracking",
        component: () => import("@/views/live-tracking/index.vue"),
    },
  //////////////////////////reports////////////////////////////////
    {
        path: "/reports/drivers",
        name: "driver-analytics",
        component: () => import("@/views/reports/drivers/index.vue"),
    },
    {
        path: "/audit-logs",
        name: "audit-logs",
        component: () => import("@/views/audit-logs/index.vue"),
    },
    {
        path: "/notification-templates",
        name: "notification-templates",
        component: () => import("@/views/notification-templates/index.vue"),
    },
    {
        path: "/notifications",
        name: "notifications",
        component: () => import("@/views/notifications/index.vue"),
    },
    {
        path: "/reports/financial",
        name: "financial-reports",
        component: () => import("@/views/reports/financial/index.vue"),
    },
    {
        path: "/driver-shifts",
        name: "driver-shifts",
        component: () => import("@/views/driver-shifts/index.vue"),
    },
  //////////////////////////customer-locations////////////////////////////////
    {
        path: "/customer-locations",
        name: "customer-locations",
        component: () => import("@/views/customer-locations/index.vue"),
    },
  //////////////////////////settings///////////////////////////////////
  {
    path: '/settings',
    name: 'settings',
    component: () => import('@/views/settings/index.vue'),
  },
  //////////////////////////activation///////////////////////////////////
  {
    path: '/activate-account',
    name: 'activate-account',
    component: () => import('@/views/activation/index.vue'),
  },
  //privacy-policy
  {
    path: '/privacy-policy',
    name: 'privacy-policy',
    component: () => import('@/views/settings/privacy-policy.vue'),
  },
  //privacy
  {
    path: '/privacy',
    name: 'privacy',
    component: () => import('@/views/settings/privacy-preview.vue'),
    meta: {
      layout: 'blank'
    },
  },
  //terms
  {
    path: '/terms-and-conditions',
    name: 'terms-and-conditions',
    component: () => import('@/views/settings/terms.vue'),
  },
  {
    path: '/terms',
    name: 'terms',
    component: () => import('@/views/settings/terms-preview.vue'),
    meta: {
      layout: 'blank'
    },
  },
  //////////////////////////pages//////////////////////////////////////
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/start-pages/Login.vue'),
    meta: {
      layout: 'blank'
    },
  },
  {
    path: '/auth/google/callback',
    name: 'google-callback',
    component: () => import('@/views/start-pages/GoogleCallback.vue'),
    meta: {
      layout: 'blank'
    },
  },
  //ForgotPassword
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('@/views/ForgotPassword.vue'),
    meta: {
      layout: 'blank'
    },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/ResetPassword.vue'),
    meta: {
      layout: 'blank'
    },
  },
  {
    path: '/register',
    name: 'pages-register',
    redirect: '/login',
  },
  {
    path: '/error-404',
    name: 'error-404',
    component: () => import('@/views/Error.vue'),
    meta: {
      layout: 'blank'
    },
  },
  {
    path: '*',
    redirect: 'error-404',
  },
]

const router = new VueRouter({
  mode: 'history',
  base: process.env.BASE_URL,
  routes,
})

// array of routes that do not require auth
const plainRoutes = [
    "/auth/google/callback",
    "/",
    "/home",
    "/login",
    "/forgot-password",
    "/reset-password",
    "/privacy",
    "/terms",
    "/error-404",
    "/error-500",
    "/customer/register",
];

router.beforeEach((to, from, next) => {

    let to_path = to.path;
    // normalize query params for route matching
    if (to_path.includes("?")) {
        to_path = to_path.split("?")[0];
    }

    let isPlainRoute = plainRoutes.includes(to_path);

    if (to.meta.customerGuest) {
        if (auth.isUserLoggedIn('customer') && Number(localStorage.getItem('customerRole')) === 1) {
            return next('/customer/beranda');
        }
        return next();
    }

    if (to.meta.customerOnly || to.path.startsWith('/customer/')) {
        if (!auth.isUserLoggedIn('customer')) return next('/login');
        if (Number(localStorage.getItem('customerRole')) !== 1) {
            localStorage.removeItem('customerToken');
            localStorage.removeItem('customerRole');
            return next('/login');
        }
        return next();
    }

    let isUserAuth = auth.isUserLoggedIn('internal');

    //1 - if plain route, go to next
    if (isPlainRoute) {
        return next();
    }
    //2 - if not plain route and not auth, redirect to login
    if (!isUserAuth) {
        return next("/login");
    }

    const role = Number(localStorage.getItem('internalRole') || localStorage.getItem('userRole'));
    if (role === 3 && !to.meta.staffOnly) return next('/staff/dashboard');
    if (to.meta.staffOnly && role !== 3) return next(role === 2 ? '/driver/beranda' : '/dashboard');
    if (role === 2 && !to.meta.driverOnly) return next('/driver/beranda');
    if (to.meta.driverOnly && role !== 2) return next('/dashboard');
    if (!to.meta.driverOnly && role !== 0 && role !== 3) return next('/driver/beranda');

    return next()
    // Specify the current path as the customState parameter, meaning it
    // will be returned to the application after auth
    // auth.login({ target: to.path });
})

export default router
