<template>

    <header class="top-header">

        <div class="university-banner">

            <!-- ======================================================
                 UNIVERSITY INFORMATION
            ======================================================= -->

            <div class="banner-text">

                <h2 class="uni-name">

                    {{ universityName }}

                </h2>


                <span class="uni-campus">

                    {{ campusType }}

                </span>

            </div>


            <!-- ======================================================
                 UNIVERSITY LOGO
            ======================================================= -->

            <div class="uni-logo-wrapper">

                <img
                    :src="universityLogo"
                    alt="University Logo"
                    class="uni-logo"
                    @error="handleLogoError"
                >

            </div>

        </div>

    </header>

</template>


<script setup>

import {
    computed,
} from 'vue'

import {
    usePage,
} from '@inertiajs/vue3'


/*
|--------------------------------------------------------------------------
| Inertia Page
|--------------------------------------------------------------------------
*/

const page =
    usePage()


/*
|--------------------------------------------------------------------------
| Current University
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| The Profile page receives:
|
| page.props.university
|
| while other University Admin pages may receive the university through:
|
| page.props.auth.university
|
| We prioritize page.props.university because this contains the freshly
| updated University information after editing the Profile.
|
*/

const university = computed(() => {

    /*
    |--------------------------------------------------------------------------
    | Profile Page University
    |--------------------------------------------------------------------------
    */

    if (
        page.props.university &&
        typeof page.props.university ===
            'object'
    ) {

        return page.props.university

    }


    /*
    |--------------------------------------------------------------------------
    | Shared Auth University
    |--------------------------------------------------------------------------
    */

    if (
        page.props.auth?.university &&
        typeof page.props.auth.university ===
            'object'
    ) {

        return page.props.auth.university

    }


    /*
    |--------------------------------------------------------------------------
    | Empty Fallback
    |--------------------------------------------------------------------------
    */

    return {}

})


/*
|--------------------------------------------------------------------------
| University Name
|--------------------------------------------------------------------------
*/

const universityName = computed(() => {

    return (
        university.value.name ??
        'SURIGAO DEL NORTE STATE UNIVERSITY'
    )

})


/*
|--------------------------------------------------------------------------
| Campus Type
|--------------------------------------------------------------------------
*/

const campusType = computed(() => {

    return (
        university.value.campus_type ??
        'MAIN CAMPUS'
    )

})


/*
|--------------------------------------------------------------------------
| University Logo
|--------------------------------------------------------------------------
|
| Supported values:
|
| universities.logo:
|     universities/logos/example.png
|
| universities.logo:
|     /storage/universities/logos/example.png
|
| universities.logo_url:
|     /storage/universities/logos/example.png
|
*/

const universityLogo = computed(() => {

    /*
    |--------------------------------------------------------------------------
    | Prefer Logo URL
    |--------------------------------------------------------------------------
    */

    let logo =
        university.value.logo_url ??
        university.value.logo ??
        ''


    /*
    |--------------------------------------------------------------------------
    | No Logo
    |--------------------------------------------------------------------------
    */

    if (
        !logo ||
        String(
            logo
        ).trim() ===
            ''
    ) {

        return '/images/snsu logo.png'

    }


    /*
    |--------------------------------------------------------------------------
    | Normalize
    |--------------------------------------------------------------------------
    */

    logo =
        String(
            logo
        ).trim()


    /*
    |--------------------------------------------------------------------------
    | External URL
    |--------------------------------------------------------------------------
    */

    if (
        logo.startsWith(
            'http://'
        ) ||
        logo.startsWith(
            'https://'
        )
    ) {

        return logo

    }


    /*
    |--------------------------------------------------------------------------
    | Existing /storage URL
    |--------------------------------------------------------------------------
    */

    if (
        logo.startsWith(
            '/storage/'
        )
    ) {

        return addCacheVersion(
            logo
        )

    }


    /*
    |--------------------------------------------------------------------------
    | Existing Absolute Local Path
    |--------------------------------------------------------------------------
    */

    if (
        logo.startsWith(
            '/'
        )
    ) {

        return addCacheVersion(
            logo
        )

    }


    /*
    |--------------------------------------------------------------------------
    | Stored Database Path
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | universities/logos/logo.png
    |
    | becomes:
    |
    | /storage/universities/logos/logo.png
    |
    */

    return addCacheVersion(
        `/storage/${logo}`
    )

})


/*
|--------------------------------------------------------------------------
| Cache Version
|--------------------------------------------------------------------------
|
| This helps prevent the browser from displaying an older cached university
| logo after the administrator uploads a replacement.
|
*/

function addCacheVersion(
    url
) {

    /*
    |--------------------------------------------------------------------------
    | Prefer Database Updated Timestamp
    |--------------------------------------------------------------------------
    */

    const version =
        university.value.updated_at
            ? encodeURIComponent(
                university.value.updated_at
            )
            : Date.now()


    /*
    |--------------------------------------------------------------------------
    | Existing Query String
    |--------------------------------------------------------------------------
    */

    if (
        url.includes(
            '?'
        )
    ) {

        return `${url}&v=${version}`

    }


    return `${url}?v=${version}`

}


/*
|--------------------------------------------------------------------------
| Logo Error
|--------------------------------------------------------------------------
*/

function handleLogoError(
    event
) {

    /*
    |--------------------------------------------------------------------------
    | Prevent Infinite Error Loop
    |--------------------------------------------------------------------------
    */

    if (
        event.target.dataset.fallbackApplied ===
            'true'
    ) {

        return

    }


    /*
    |--------------------------------------------------------------------------
    | Mark Fallback
    |--------------------------------------------------------------------------
    */

    event.target.dataset.fallbackApplied =
        'true'


    /*
    |--------------------------------------------------------------------------
    | Default Logo
    |--------------------------------------------------------------------------
    */

    event.target.src =
        '/images/snsu logo.png'

}

</script>


<style scoped>

/* ==========================================================
   HEADER
========================================================== */

.top-header {
    width: 100%;

    height: 80px;

    background:
        #58761C;

    display:
        flex;

    justify-content:
        flex-end;

    align-items:
        center;

    padding:
        0
        40px;

    position:
        relative;

    box-sizing:
        border-box;
}


/* ==========================================================
   UNIVERSITY PILL
========================================================== */

.university-banner {
    position:
        relative;

    width:
        540px;

    height:
        54px;

    background:
        #F7F4ED;

    border-radius:
        999px;

    display:
        flex;

    align-items:
        center;

    padding-left:
        42px;

    padding-right:
        72px;

    box-sizing:
        border-box;
}


/* ==========================================================
   TEXT
========================================================== */

.banner-text {
    width:
        100%;

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    text-align:
        left;
}


.uni-name {
    margin:
        0;

    font-size:
        15px;

    font-weight:
        700;

    color:
        #54100F;

    text-transform:
        uppercase;

    line-height:
        1.2;
}


.uni-campus {
    margin-top:
        3px;

    font-size:
        11px;

    font-weight:
        600;

    color:
        #233E47;

    text-transform:
        uppercase;
}


/* ==========================================================
   UNIVERSITY LOGO WRAPPER
========================================================== */

.uni-logo-wrapper {
    position:
        absolute;

    right:
        -34px;

    top:
        50%;

    transform:
        translateY(-50%);

    width:
        70px;

    height:
        70px;

    border-radius:
        50%;

    background:
        #FFFFFF;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    overflow:
        hidden;

    box-shadow:
        0
        6px
        18px
        rgba(
            0,
            0,
            0,
            .25
        );
}


/* ==========================================================
   UNIVERSITY LOGO
========================================================== */

.uni-logo {
    display:
        block;

    width:
        100%;

    height:
        100%;

    object-fit:
        cover;
}


/* ==========================================================
   RESPONSIVE
========================================================== */

@media (
    max-width: 768px
) {

    .top-header {
        padding:
            0
            25px;
    }


    .university-banner {
        width:
            min(
                480px,
                calc(100% - 25px)
            );

        padding-left:
            25px;

        padding-right:
            60px;
    }


    .uni-name {
        font-size:
            13px;
    }


    .uni-logo-wrapper {
        width:
            62px;

        height:
            62px;

        right:
            -26px;
    }

}


@media (
    max-width: 500px
) {

    .top-header {
        height:
            72px;

        padding:
            0
            20px
            0
            12px;
    }


    .university-banner {
        width:
            calc(100% - 15px);

        height:
            48px;

        padding-left:
            18px;

        padding-right:
            52px;
    }


    .uni-name {
        font-size:
            11px;

        overflow:
            hidden;

        white-space:
            nowrap;

        text-overflow:
            ellipsis;
    }


    .uni-campus {
        font-size:
            9px;
    }


    .uni-logo-wrapper {
        width:
            56px;

        height:
            56px;

        right:
            -20px;
    }

}

</style>