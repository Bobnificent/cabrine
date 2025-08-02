@extends('layouts.back-end.app')

@section('title', \App\CPU\translate('theme_setup'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/back-end/vendor/swiper/swiper-bundle.min.css')}}"/>
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Title -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 pb-2">
            <h2 class="h1 mb-0 text-capitalize d-flex align-items-center gap-2">
                <img src="{{asset('/public/assets/back-end/img/teheme-setup.png')}}" alt="">
                {{\App\CPU\translate('system_setup')}}
            </h2>

            <div class="modal fade" id="settingModal" tabindex="-1" aria-labelledby="settingModal" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0 d-flex justify-content-end">
                            <button
                                type="button"
                                class="btn-close border-0"
                                data-dismiss="modal"
                                aria-label="Close"
                            ><i class="tio-clear"></i></button>
                        </div>
                        <div class="modal-body px-4 px-sm-5 pt-0 text-center">
                            <div class="row g-2 g-sm-3 mt-lg-0">
                                <div class="col-12">
                                    <div class="swiper mySwiper pb-3">
                                        <div class="swiper-wrapper">
                                            <div class="swiper-slide">
                                                <img src="{{asset('public/assets/back-end/img/slider-1.png')}}"
                                                     loading="lazy"
                                                     alt="" class="dark-support rounded">
                                            </div>
                                            <div class="swiper-slide">
                                                <div class="d-flex flex-column align-items-center mx-w450 mx-auto">
                                                    <img src="{{asset('public/assets/back-end/img/slider-2.png')}}"
                                                         loading="lazy"
                                                         alt="" class="dark-support rounded mb-4">
                                                    <p>
                                                        {{ translate('get_your_zip_file_from_the_purchased_theme_and_upload_it_and_activate_theme_with_your_Codecanyon_username_and_purchase_code') }}
                                                        .
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="swiper-slide">
                                                <div class="d-flex flex-column align-items-center mx-w450 mx-auto">
                                                    <img src="{{asset('public/assets/back-end/img/slider-3.png')}}"
                                                         loading="lazy"
                                                         alt="" class="dark-support rounded mb-4">
                                                    <p>
                                                        {{ translate('now_you’ll_be_successfully_able_to_use_the_theme_for_your_6Valley_website') }}
                                                    </p>
                                                    <p>
                                                        {{ translate('N:B you_can_upload_only_6Valley’s_theme_templates') }}
                                                        .
                                                    </p>
                                                    <button class="btn btn-primary px-10 mt-3"
                                                            data-dismiss="modal">{{ translate('Got_It') }}</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="swiper-pagination"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- End Page Title -->

        <!-- Inlile Menu -->
        @include('admin-views.business-settings.system-settings-inline-menu')
        <!-- End Inlile Menu -->
    </div>

@endsection

@push('script')
    <script src="{{ asset('public/assets/back-end/vendor/swiper/swiper-bundle.min.js')}}"></script>

    <script>
        function readUrl(input) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();
                reader.onload = (e) => {
                    let imgData = e.target.result;
                    let imgName = input.files[0].name;
                    input.setAttribute("data-title", imgName);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <script>
        function theme_install() {
            $.ajaxSetup({
                headers: {
                    "X-CSRF-TOKEN": $('meta[name="_token"]').attr("content"),
                },
            });

            var formData = new FormData(document.getElementById('theme_form'));

            $.ajax({
                type: 'POST',
                url: "{{route('admin.business-settings.web-config.theme.install')}}",
                data: formData,
                processData: false,
                contentType: false,
                xhr: function () {
                    var xhr = new window.XMLHttpRequest();
                    $('#progress-bar').show();

                    // Listen to the upload progress event
                    xhr.upload.addEventListener("progress", function (e) {
                        if (e.lengthComputable) {
                            var percentage = Math.round((e.loaded * 100) / e.total);
                            $("#uploadProgress").val(percentage);
                            $("#progress-label").text(percentage + "%");
                        }
                    }, false);

                    return xhr;
                },
                beforeSend: function () {
                    $('#upload_theme').attr('disabled');
                },
                success: function (response) {
                    if (response.status == 'error') {
                        $('#progress-bar').hide();
                        toastr.error(response.message);
                    } else if (response.status == 'success') {
                        toastr.success(response.message);
                        location.reload();
                    }
                },
                complete: function () {
                    $('#upload_theme').removeAttr('disabled');
                },
            });
        }

        function theme_publish(theme) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.business-settings.web-config.theme.publish')}}',
                data: {
                    theme
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.flag === 'inactive') {
                        $('#activateData').empty().html(data.view);
                        $("#activatedThemeModal").addClass('bg-soft-dark').modal("show");
                    } else {
                        if (data.errors) {
                            for (var i = 0; i < data.errors.length; i++) {
                                toastr.error(data.errors[i].message, {
                                    CloseButton: true,
                                    ProgressBar: true
                                });
                            }
                        } else {
                            toastr.success('{{ translate("updated successfully!") }}', {
                                CloseButton: true,
                                ProgressBar: true
                            });
                            setTimeout(function () {
                                location.reload()
                            }, 2000);
                        }
                    }
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        }

        function theme_delete(theme) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.post({
                url: '{{route('admin.business-settings.web-config.theme.delete')}}',
                data: {
                    theme
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    if (data.status === 'success') {
                        setTimeout(function () {
                            location.reload()
                        }, 2000);

                        toastr.success(data.message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    } else if (data.status === 'error') {
                        toastr.error(data.message, {
                            CloseButton: true,
                            ProgressBar: true
                        });
                    }
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        }

        var swiper = new Swiper(".mySwiper", {
            pagination: {
                el: ".swiper-pagination",
                dynamicBullets: true,
            },
        });
    </script>
@endpush
