;(function($) {
    'use strict';
    $(window).on( 'elementor/frontend/init', function() {




        var GlobalJSLoad = function() {
            if ($('[data-bg-src]').length > 0) {
                $('[data-bg-src]').each(function () {
                  var src = $(this).attr('data-bg-src');
                  $(this).css('background-image', 'url(' + src + ')');
                  $(this).removeAttr('data-bg-src').addClass('background-image');
                });
            };  

            $("[data-slick-next]").each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    $($(this).data("slick-next")).slick("slickNext");
                });
            });

            $("[data-slick-prev]").each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    $($(this).data("slick-prev")).slick("slickPrev");
                });
            });

        };

    elementorFrontend.hooks.addAction('frontend/element_ready/global', GlobalJSLoad);




        

        //menu list //

        elementorFrontend.hooks.addAction('frontend/element_ready/pizzermenulist.default',function($scope) {
            let $menu_carousels1 = $scope.find('.menu-carousel-1');
            $menu_carousels1.not('.slick-initialized').slick({
                dots: true,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 4,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 3,
                        dots: true,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                        dots: true,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2,
                        dots: true,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
            });

            let $menu_carousels3 = $scope.find('.menu-carousel-3');
            $menu_carousels3.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 3,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 3,
                        dots: true,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
            });

            let $menu_carousels2 = $scope.find('.menu-carousel-2');
            $menu_carousels2.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 4,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
            }); 
            $("[data-slick-next]").each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    $($(this).data("slick-next")).slick("slickNext");
                });
            });

            $("[data-slick-prev]").each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    $($(this).data("slick-prev")).slick("slickPrev");
                });
            });
            if ($('[data-bg-src]').length > 0) {
                $('[data-bg-src]').each(function () {
                  var src = $(this).attr('data-bg-src');
                  $(this).css('background-image', 'url(' + src + ')');
                  $(this).removeAttr('data-bg-src').addClass('background-image');
                });
            };
        });

        //experience//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerexperiencebox.default',function($scope) {
            /*----------- 15. Counter Up ----------*/
            $(".counter-number").counterUp({
                delay: 10,
                time: 1000,
            });
        });
        //features//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerfeature.default',function($scope) {
            if ($('[data-bg-src]').length > 0) {
                $('[data-bg-src]').each(function () {
                  var src = $(this).attr('data-bg-src');
                  $(this).css('background-image', 'url(' + src + ')');
                  $(this).removeAttr('data-bg-src').addClass('background-image');
                });
            };
        });
        //discount//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzercart.default',function($scope) {
            if ($('[data-bg-src]').length > 0) {
                $('[data-bg-src]').each(function () {
                  var src = $(this).attr('data-bg-src');
                  $(this).css('background-image', 'url(' + src + ')');
                  $(this).removeAttr('data-bg-src').addClass('background-image');
                });
            };
        });
        //COUNTER//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzercounter.default',function($scope) {
            // $(".counter-number").counterUp({
            //     delay: 10,
            //     time: 1000,
            // });
        });
        //team//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerteam.default',function($scope) {
            let $team_carousels = $scope.find('.team-carousel');
            $team_carousels.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="far fa-arrow-left"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="far fa-arrow-right"></i></button>',
                speed: 1000,
                slidesToShow: $team_carousels.data('slide-show'),
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: $team_carousels.data('lg-slide-show'),
                        arrows: true,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: $team_carousels.data('md-slide-show'),
                        arrows: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: $team_carousels.data('md-slide-show'),
                        arrows: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: $team_carousels.data('sm-slide-show'),
                        arrows: false,
                    }
                }
                ]
            });
        });
        //team//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerteamv2.default',function($scope) {
            let $team_carousels2 = $scope.find('.team-slider');
            $team_carousels2.not('.slick-initialized').slick({
                dots: true,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                prevArrow: '<button type="button" class="slick-prev"><i class="far fa-arrow-left"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="far fa-arrow-right"></i></button>',
                speed: 1000,
                slidesToShow: $team_carousels2.data('slide-show'),
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: $team_carousels2.data('lg-slide-show'),
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: $team_carousels2.data('md-slide-show'),
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: $team_carousels2.data('md-slide-show'),
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: $team_carousels2.data('sm-slide-show'),
                    }
                }
                ]
            });
        });
        //blog//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerblogpost.default',function($scope) {
            let $team_carousels = $scope.find('.blog_slider1');
            $team_carousels.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="far fa-arrow-left"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="far fa-arrow-right"></i></button>',
                speed: 1000,
                slidesToShow: $team_carousels.data('slide-show'),
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: $team_carousels.data('lg-slide-show'),
                        arrows: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: $team_carousels.data('md-slide-show'),
                        arrows: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: $team_carousels.data('sm-slide-show'),
                        arrows: false,
                    }
                }
                ]
            });
        });

        //testimonials//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzertestimonialslider.default',function($scope) {
            let $testimonials_carousels = $scope.find('.testimonial-carousel1');
            $testimonials_carousels.not('.slick-initialized').slick({
                dots: $testimonials_carousels.data('dots'),
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: $testimonials_carousels.data('slide-show'),
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: $testimonials_carousels.data('lg-slide-show'),
                        dots: $testimonials_carousels.data('dots'),
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: $testimonials_carousels.data('md-slide-show'),
                        dots: $testimonials_carousels.data('dots'),
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: $testimonials_carousels.data('sm-slide-show'),
                        dots: $testimonials_carousels.data('dots'),
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: $testimonials_carousels.data('sm-slide-show'),
                        dots: false,
                    }
                }
                ]
            });

            let $testimonials_carousels2 = $scope.find('.testimonial-carousel2');
            $testimonials_carousels2.not('.slick-initialized').slick({
                dots: $testimonials_carousels2.data('dots'),
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: $testimonials_carousels2.data('slide-show'),
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: $testimonials_carousels2.data('lg-slide-show'),
                        dots: $testimonials_carousels2.data('dots'),
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: $testimonials_carousels2.data('md-slide-show'),
                        dots: $testimonials_carousels2.data('dots'),
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: $testimonials_carousels2.data('sm-slide-show'),
                        dots: $testimonials_carousels2.data('dots'),
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: $testimonials_carousels2.data('sm-slide-show'),
                        dots: false,
                    }
                }
                ]
            });

            let $testimonials_carousels3 = $scope.find('.tab-slide');
            $testimonials_carousels3.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="far fa-arrow-left"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="far fa-arrow-right"></i></button>',
                speed: 1000,
                slidesToShow: 5,
                asNavFor: '.testi-grid-slide',
                centerMode: true,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 5,
                        arrows: true,
                        centerMode: true,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 5,
                        arrows: true,
                        centerMode: true,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 5,
                        arrows: true,
                        centerMode: true,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 5,
                        arrows: true,
                        centerMode: true,
                    }
                }
                ]
            });

            let $testimonials_carousels4 = $scope.find('.testi-grid-slide');
            $testimonials_carousels4.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                asNavFor: '.tab-slide',
                fade: true,
                arrows: false,
                speed: 1000,
                slidesToShow: 1,
                slidesToScroll: 1,
            });

        });
        //shape mockup//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzershapeimage.default',function($scope) {
            /*----------- 16. Shape Mockup ----------*/
              $.fn.shapeMockup = function () {
                var $shape = $(this);
                $shape.each(function() {
                  var $currentShape = $(this),
                  shapeTop = $currentShape.data('top'),
                  shapeRight = $currentShape.data('right'),
                  shapeBottom = $currentShape.data('bottom'),
                  shapeLeft = $currentShape.data('left');
                  $currentShape.css({
                    top: shapeTop,
                    right: shapeRight,
                    bottom: shapeBottom,
                    left: shapeLeft,
                  }).removeAttr('data-top')
                  .removeAttr('data-right')
                  .removeAttr('data-bottom')
                  .removeAttr('data-left')
                  .closest('.elementor-widget , .elementor-widget-wrap').css('position', 'static')
                  .closest('section').addClass('shape-mockup-wrap');
                });
              };

              if ($('.shape-mockup')) {
                $('.shape-mockup').shapeMockup();
              }
            
        });

        //menu board//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzermenuboard.default',function($scope) {
            if ($('[data-bg-src]').length > 0) {
                $('[data-bg-src]').each(function () {
                  var src = $(this).attr('data-bg-src');
                  $(this).css('background-image', 'url(' + src + ')');
                  $(this).removeAttr('data-bg-src').addClass('background-image');
                });
            }; 
            $(".masonary-active").imagesLoaded(function () {
                var $filter = ".masonary-active",
                    $filterItem = ".filter-item",
                    $filterMenu = ".filter-menu-active";

                if ($($filter).length > 0) {
                    var $grid = $($filter).isotope({
                        itemSelector: $filterItem,
                        filter: "*",
                        masonry: {
                            // use outer width of grid-sizer for columnWidth
                            columnWidth: 1,
                        },
                    });
                }
            }); 
        });

        //contact us  board//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzercontactinfo.default',function($scope) {
            if ($('[data-bg-src]').length > 0) {
                $('[data-bg-src]').each(function () {
                  var src = $(this).attr('data-bg-src');
                  $(this).css('background-image', 'url(' + src + ')');
                  $(this).removeAttr('data-bg-src').addClass('background-image');
                });
            };  
        });
        //gallery//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzergallery.default',function($scope) {
            let $menu_carousels1 = $scope.find('.gallery-carousel');
            $menu_carousels1.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 4,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
            }); 
        });
        //custom cat//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzercustomcat.default',function($scope) {
            let $menu_carousels1 = $scope.find('.custom-cat-carousel');
            $menu_carousels1.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 10,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1600,
                    settings: {
                        slidesToShow: 8,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 7,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                }
                ]
            }); 
        });

        //product tab //
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerproducttab.default',function($scope) {
            $(".filter-active").imagesLoaded(function () {
                var $filter = ".filter-active",
                    $filterItem = ".filter-item",
                    $filterMenu = ".filter-menu-active";

                if ($($filter).length > 0) {
                    var $grid = $($filter).isotope({
                        itemSelector: $filterItem,
                        filter: "*",
                        // masonry: {
                        //     // use outer width of grid-sizer for columnWidth
                        //     columnWidth: 1,
                        // },
                    });

                    // filter items on button click
                    $($filterMenu).on("click", "button", function () {
                        var filterValue = $(this).attr("data-filter");
                        $grid.isotope({
                            filter: filterValue,
                        });
                    });

                    // Menu Active Class
                    $($filterMenu).on("click", "button", function (event) {
                        event.preventDefault();
                        $(this).addClass("active");
                        $(this).siblings(".active").removeClass("active");
                    });
                }
                $('[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                    $($filter).isotope({
                        filter: "*",
                    });
                    $('.filter-menu-active button:first-child').addClass('active').siblings().removeClass('active');
                });
            }); 
        });
        //product advance tab //
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerproductadvancetab.default',function($scope) {
            $(".filter-active").imagesLoaded(function () {
                var $filter = ".filter-active",
                    $filterItem = ".filter-item",
                    $filterMenu = ".filter-menu-active";

                if ($($filter).length > 0) {
                    var $grid = $($filter).isotope({
                        itemSelector: $filterItem,
                        filter: "*",
                        // masonry: {
                        //     // use outer width of grid-sizer for columnWidth
                        //     columnWidth: 1,
                        // },
                    });

                    // filter items on button click
                    $($filterMenu).on("click", "button", function () {
                        var filterValue = $(this).attr("data-filter");
                        $grid.isotope({
                            filter: filterValue,
                        });
                    });

                    // Menu Active Class
                    $($filterMenu).on("click", "button", function (event) {
                        event.preventDefault();
                        $(this).addClass("active");
                        $(this).siblings(".active").removeClass("active");
                    });
                }
                $('[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                    $($filter).isotope({
                        filter: "*",
                    });
                    $('.filter-menu-active button:first-child').addClass('active').siblings().removeClass('active');
                });
            }); 
        });


        // -----------------------------------------new home-----------------------------------------//




        //product//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerproductv2.default',function($scope) {
            let $th_prod_1 = $scope.find('.th-prod-1-carousel');
            $th_prod_1.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: true,
                prevArrow: '<button type="button" class="slick-prev"><i class="far fa-arrow-left"></i></button>',
                nextArrow: '<button type="button" class="slick-next"><i class="far fa-arrow-right"></i></button>',
                speed: 1000,
                slidesToShow: $th_prod_1.data('slide-show'),
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: $th_prod_1.data('lg-slide-show'),
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: $th_prod_1.data('md-slide-show'),
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: $th_prod_1.data('md-slide-show'),
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: $th_prod_1.data('sm-slide-show'),
                        dots: false,
                    }
                }
                ]
            });
        });


        elementorFrontend.hooks.addAction('frontend/element_ready/pizzergallery2.default',function($scope) {
            let $menu_carousels1 = $scope.find('.gal-carousel');
            $menu_carousels1.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 8,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1600,
                    settings: {
                        slidesToShow: 8,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 7,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                }
                ]
            }); 

            let $logos_carousels33 = $scope.find('.gal-carousel6');
            $logos_carousels33.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 4,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
            });

            $("[data-slick-next]").each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    $($(this).data("slick-next")).slick("slickNext");
                });
            });

            $("[data-slick-prev]").each(function () {
                $(this).on("click", function (e) {
                    e.preventDefault();
                    $($(this).data("slick-prev")).slick("slickPrev");
                });
            });

        });


        //custom cat//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzercustomcat2.default',function($scope) {
            let $menu_carousels1 = $scope.find('.cat-carousel');
            $menu_carousels1.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 5,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1600,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                }
                ]
            });


            let $menu_carousels3 = $scope.find('.cat-carousel2');
            $menu_carousels3.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 6,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1600,
                    settings: {
                        slidesToShow: 6,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                }
                ]
            }); 
        });


         //Testimonials//
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzertestimonialslider2.default',function($scope) {
            let $testimonials_v2 = $scope.find('.testi-carousel6');
            $testimonials_v2.not('.slick-initialized').slick({
                dots: true,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 1,
                slidesToScroll: 1,
                responsive: [
                {
                    breakpoint: 1199,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                },{
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
                
            });
            let $testimonials_v3 = $scope.find('.testi-carousel-3');
            $testimonials_v3.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 2,
                slidesToScroll: 1,
                responsive: [
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 1,
                        dots: false,
                    }
                }
                ]
                
            });

            let $testimonials_v5 = $scope.find('.testi-carousel-4');
            $testimonials_v5.not('.slick-initialized').slick({
                dots: true,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 3,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 3,
                        dots: true,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2,
                        dots: true,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 2,
                        dots: true,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 1,
                        dots: true,
                    }
                }
                ]
                
            });
        });


        //logo carosel //
        elementorFrontend.hooks.addAction('frontend/element_ready/pizzerclientlogo5.default',function($scope) {
            let $logos_carousels1 = $scope.find('.logos-carousel');
            $logos_carousels1.not('.slick-initialized').slick({
                dots: false,
                infinite: true,
                autoplay: true,
                autoplaySpeed: 6000,
                fade: false,
                arrows: false,
                speed: 1000,
                slidesToShow: 6,
                slidesToScroll: 1,
                responsive: [{
                    breakpoint: 1400,
                    settings: {
                        slidesToShow: 5,
                        dots: false,
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 4,
                        dots: false,
                    }
                },
                {
                    breakpoint: 768,
                    settings: {
                        slidesToShow: 3,
                        dots: false,
                    }
                },
                {
                    breakpoint: 576,
                    settings: {
                        slidesToShow: 2,
                        dots: false,
                    }
                }
                ]
            }); 
        });

        // //group image//
        // elementorFrontend.hooks.addAction('frontend/element_ready/pizzergroupimage2.default',function($scope) {
        //     if ($('[data-bg-src]').length > 0) {
        //         $('[data-bg-src]').each(function () {
        //           var src = $(this).attr('data-bg-src');
        //           $(this).css('background-image', 'url(' + src + ')');
        //           $(this).removeAttr('data-bg-src').addClass('background-image');
        //         });
        //     };
        // });
        // //group image//
        // elementorFrontend.hooks.addAction('frontend/element_ready/pizzermenulistv2.default',function($scope) {
        //     if ($('[data-bg-src]').length > 0) {
        //         $('[data-bg-src]').each(function () {
        //           var src = $(this).attr('data-bg-src');
        //           $(this).css('background-image', 'url(' + src + ')');
        //           $(this).removeAttr('data-bg-src').addClass('background-image');
        //         });
        //     };
        // });



    });
}(jQuery));
