(function ($) {
    'use strict';

    $(function () {
        var destinationModalElement = document.getElementById('destinationModal');
        var shipModalElement = document.getElementById('shipModal');
        var destinationModal = destinationModalElement && window.appModal ? window.appModal.getOrCreateInstance(destinationModalElement) : null;
        var shipModal = shipModalElement && window.appModal ? window.appModal.getOrCreateInstance(shipModalElement) : null;

        var destinationState = {
            isEdit: false,
            id: 0,
            index: -1
        };

        var shipState = {
            isEdit: false,
            id: 0,
            index: -1
        };

        function resetDestinationForm() {
            destinationState.isEdit = false;
            destinationState.id = 0;
            destinationState.index = -1;
            $('#inp_name').val('');
            $('#inp_name_e').val('');
            $('#destinationModalTitle').text('行先を追加');
        }

        function resetShipForm() {
            shipState.isEdit = false;
            shipState.id = 0;
            shipState.index = -1;
            $('#inp_ship_name').val('');
            $('#inp_ship_name_e').val('');
            $('#shipModalTitle').text('艇名を追加');
        }

        function postJson(url, payload, onSuccess) {
            $.ajax({
                type: 'POST',
                url: url,
                dataType: 'json',
                data: payload,
                success: function (response) {
                    onSuccess(response || {});
                },
                error: function () {
                    window.location.reload();
                }
            });
        }

        function requestDeleteConfirmation(message, onConfirm) {
            if (typeof window.appConfirmDialog !== 'function') {
                if (window.confirm(message)) {
                    onConfirm();
                }
                return;
            }

            window.appConfirmDialog({
                message: message,
                confirmText: '削除する',
                confirmButtonClass: 'adm-btn adm-btn-danger'
            }).then(function (confirmed) {
                if (confirmed) {
                    onConfirm();
                }
            });
        }

        $('#btnNew').on('click', function () {
            resetDestinationForm();
            if (destinationModal) {
                destinationModal.show();
            }
        });

        $('#btnShipNew').on('click', function () {
            resetShipForm();
            if (shipModal) {
                shipModal.show();
            }
        });

        $('#btnReg').on('click', function () {
            var name = $('#inp_name').val().trim();
            var nameEnglish = $('#inp_name_e').val().trim();

            if (name === '') {
                $('#inp_name').trigger('focus');
                return;
            }

            if (destinationModal) {
                destinationModal.hide();
            }

            if (destinationState.isEdit) {
                postJson('cgi/editstation.php', {
                    id: destinationState.id,
                    name: name,
                    name_e: nameEnglish
                }, function () {
                    $('.station_name').eq(destinationState.index).text(name);
                    $('.station_name_e').eq(destinationState.index).text(nameEnglish);
                });
                return;
            }

            postJson('cgi/addstations.php', {
                name: name,
                name_e: nameEnglish
            }, function () {
                window.location.reload();
            });
        });

        $('#btnShipReg').on('click', function () {
            var name = $('#inp_ship_name').val().trim();
            var nameEnglish = $('#inp_ship_name_e').val().trim();

            if (name === '') {
                $('#inp_ship_name').trigger('focus');
                return;
            }

            if (shipModal) {
                shipModal.hide();
            }

            if (shipState.isEdit) {
                postJson('cgi/editship.php', {
                    id: shipState.id,
                    name: name,
                    name_e: nameEnglish
                }, function () {
                    $('.ship_name').eq(shipState.index).text(name);
                    $('.ship_name_e').eq(shipState.index).text(nameEnglish);
                });
                return;
            }

            postJson('cgi/addships.php', {
                name: name,
                name_e: nameEnglish
            }, function () {
                window.location.reload();
            });
        });

        $('.btnEdit').on('click', function () {
            destinationState.isEdit = true;
            destinationState.id = parseInt($(this).val(), 10) || 0;
            destinationState.index = $('.btnEdit').index(this);
            $('#inp_name').val($('.station_name').eq(destinationState.index).text().trim());
            $('#inp_name_e').val($('.station_name_e').eq(destinationState.index).text().trim());
            $('#destinationModalTitle').text('行先を編集');
            if (destinationModal) {
                destinationModal.show();
            }
        });

        $('.btnShipEdit').on('click', function () {
            shipState.isEdit = true;
            shipState.id = parseInt($(this).val(), 10) || 0;
            shipState.index = $('.btnShipEdit').index(this);
            $('#inp_ship_name').val($('.ship_name').eq(shipState.index).text().trim());
            $('#inp_ship_name_e').val($('.ship_name_e').eq(shipState.index).text().trim());
            $('#shipModalTitle').text('艇名を編集');
            if (shipModal) {
                shipModal.show();
            }
        });

        $('.btnDel').on('click', function () {
            var id = parseInt($(this).val(), 10) || 0;
            if (id <= 0) {
                return;
            }
            requestDeleteConfirmation('この行先を削除しますか？', function () {
                postJson('cgi/delstation.php', { id: id }, function () {
                    window.location.reload();
                });
            });
        });

        $('.btnShipDel').on('click', function () {
            var id = parseInt($(this).val(), 10) || 0;
            if (id <= 0) {
                return;
            }
            requestDeleteConfirmation('この艇名を削除しますか？', function () {
                postJson('cgi/delship.php', { id: id }, function () {
                    window.location.reload();
                });
            });
        });

        if (destinationModalElement) {
            destinationModalElement.addEventListener('app-modal:hidden', resetDestinationForm);
        }
        if (shipModalElement) {
            shipModalElement.addEventListener('app-modal:hidden', resetShipForm);
        }
    });
})(jQuery);
