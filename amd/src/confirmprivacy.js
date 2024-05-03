// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX helper for the inline editing a value.
 *
 * This script is automatically included from template core/inplace_editable
 * It registers a click-listener on [data-inplaceeditablelink] link (the "inplace edit" icon),
 * then replaces the displayed value with an input field. On "Enter" it sends a request
 * to web service core_update_inplace_editable, which invokes the specified callback.
 * Any exception thrown by the web service (or callback) is displayed as an error popup.
 *
 * @module     local_campusconnect/confirmprivacy
 * @copyright  2024 Georg Maißer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.3
 */

// import Notification from 'core/notification';
 import ModalForm from 'core_form/modalform';

 import {
    get_string as getString
        }
        from 'core/str';

/**
 * @param {int} courseid
 * @param {string} targeturl
 * @param {string} returnurl
 */
export const init = (courseid, targeturl, returnurl) => {

    confirmPrivacyModal(courseid, targeturl, returnurl);
};

/**
 * Confirm privacy modal.
 * @param {int} courseid
 * @param {string} targeturl
 * @param {string} returnurl
 */
function confirmPrivacyModal(courseid, targeturl, returnurl) {

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_campusconnect\\form\\modal_confirmprivacy",
        // Add as many arguments as you need, they will be passed to the form:
        args: {
            'courseid': courseid,
            'targeturl': targeturl
        },
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {
            title: getString('confirmprivacytitle', 'local_campusconnect'),
        },
        // DOM element that should get the focus after the modal dialogue is closed:
        // returnFocus: element
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {

        // eslint-disable-next-line no-console
        console.log(e.detail);

        if (e.detail.targeturl) {

            var url = new URL(e.detail.targeturl);

            url.searchParams.append('privacyread', 1);
            window.location.href = url;
        }

    });

    modalForm.addEventListener(modalForm.events.CANCEL_BUTTON_PRESSED, (e) => {

        // eslint-disable-next-line no-console
        console.log('returnurl', e, returnurl);

        window.location.href = returnurl;

    });

    // Show the form.
    modalForm.show();

}