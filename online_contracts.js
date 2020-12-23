
define(['jquery', 'fab/fabrik'], function (jQuery, Fabrik) {
    'use strict';
    var FabrikOnlineContracts = new Class({
        Implements: [Events],

        initialize: function (options) {
            this.options = options;

            if (this.options.view === 'details') {
                this.createButtons();
            }

            if (this.options.view === 'form') {
                this.removeIcons();
            }
        },
        
        createButtons: function () {
            var group, center, button_group, button, button2, sub_group, sub_group_elements, row_striped, row_fluid, button_name = 'gerar_pdf', button_name2 = 'gerar_doc';

            group = document.getElementById(this.options.group);
            sub_group = document.createElement('div');
            sub_group.setAttribute('class', 'fabrikSubGroup');
            sub_group_elements = document.createElement('div');
            sub_group_elements.setAttribute('class', 'fabrikSubGroupElements');
            row_striped = document.createElement('div');
            row_striped.setAttribute('class', 'row-striped');
            row_fluid = document.createElement('div');
            row_fluid.setAttribute('class', 'row-fluid fabrikElementContainer');
            center = document.createElement('center');

            button_group = document.createElement('div');
            button_group.setAttribute('class', 'btn-group');
            button_group.setAttribute('style', 'margin: 10px;');

            button = document.createElement('a');
            button.setAttribute('type', 'button');
            button.setAttribute('class', 'btn btn-primary');
            button.setAttribute('href', this.options.url_pdf);
            button.setAttribute('download', '');
            button.setAttribute('id', button_name);
            button.setAttribute('style', 'margin: 5px;');
            button.innerHTML = 'Gerar PDF';

            button2 = document.createElement('a');
            button2.setAttribute('type', 'button');
            button2.setAttribute('class', 'btn btn-primary');
            button2.setAttribute('href', this.options.url_doc);
            button2.setAttribute('download', '');
            button2.setAttribute('id', button_name2);
            button2.setAttribute('style', 'margin: 5px;');
            button2.innerHTML = 'Gerar DOC';

            button_group.appendChild(button);
            button_group.appendChild(button2);

            center.appendChild(button_group);
            row_fluid.appendChild(center);
            row_striped.appendChild(row_fluid);
            sub_group_elements.appendChild(row_striped);
            sub_group.appendChild(sub_group_elements);
            group.appendChild(sub_group);
        },

        removeIcons: function () {
            var divs_icon = document.getElementsByClassName('fabrikGroupRepeater');

            for (var i=0; i<divs_icon.length; i++) {
                if (divs_icon.item(i)) {
                    divs_icon.item(i).setAttribute('style', 'display: none;');
                }
            }
        }
    });

    return FabrikOnlineContracts;
});