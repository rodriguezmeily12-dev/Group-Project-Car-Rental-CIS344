/* =====================================================================
   DMM Bronx Auto System - front-end behavior
   These checks make the forms friendlier. The PHP code checks
   everything again on the server, so nothing depends on JavaScript.
   ===================================================================== */

document.addEventListener("DOMContentLoaded", function () {
    setUpConfirmButtons();
    setUpTableSearch();
    setUpRentalForm();
    setUpPaymentForm();
});

/* Turn a number into dollars, for example 1250 -> "$1,250.00" */
function formatMoney(amount) {
    return "$" + amount.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/* ---------------------------------------------------------------------
   1. Ask "Are you sure?" before forms marked with data-confirm
   (deleting a customer, marking a car returned, and so on)
   ------------------------------------------------------------------- */
function setUpConfirmButtons() {
    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
}

/* ---------------------------------------------------------------------
   2. Live search box above a table: hides rows that don't match
   <input data-table-search="#table-id">
   ------------------------------------------------------------------- */
function setUpTableSearch() {
    document.querySelectorAll("input[data-table-search]").forEach(function (box) {
        var table = document.querySelector(box.dataset.tableSearch);
        if (!table) return;

        var rows = table.querySelectorAll("tbody tr:not(.empty-row)");
        var noMatches = document.querySelector(box.dataset.tableSearch + "-no-matches");

        box.addEventListener("input", function () {
            var query = box.value.trim().toLowerCase();
            var shown = 0;

            rows.forEach(function (row) {
                var matches = row.textContent.toLowerCase().indexOf(query) !== -1;
                row.hidden = !matches;
                if (matches) shown++;
            });

            if (noMatches) {
                noMatches.style.display = (shown === 0 && rows.length > 0) ? "block" : "none";
            }
        });
    });
}

/* ---------------------------------------------------------------------
   3. Add Rental form: check the dates and show the estimated cost
   (days x the selected car's daily rate)
   ------------------------------------------------------------------- */
function setUpRentalForm() {
    var form = document.getElementById("rental-form");
    if (!form) return;

    var car = form.querySelector("#car_id");
    var start = form.querySelector("#rental_date");
    var end = form.querySelector("#return_date");
    var dateHint = form.querySelector("#date-hint");
    var estimate = form.querySelector("#estimate");

    function update() {
        // The return date can't be before the pickup date
        end.min = start.value || "";

        var option = car.options[car.selectedIndex];
        var rate = option ? parseFloat(option.dataset.rate) : NaN;

        if (!start.value || !end.value) {
            end.setCustomValidity("");
            dateHint.textContent = "";
            dateHint.classList.remove("is-error");
            estimate.textContent = "Pick a car and both dates to see the cost.";
            return;
        }

        var startDate = new Date(start.value + "T00:00:00");
        var endDate = new Date(end.value + "T00:00:00");
        var days = Math.round((endDate - startDate) / 86400000);

        if (days < 0) {
            end.setCustomValidity("The return date must be on or after the pickup date.");
            dateHint.textContent = "The return date must be on or after the pickup date.";
            dateHint.classList.add("is-error");
            estimate.textContent = "Fix the dates to see the cost.";
            return;
        }

        end.setCustomValidity("");
        dateHint.classList.remove("is-error");

        // A same-day rental is charged as 1 day (matches the SQL)
        var billedDays = Math.max(days, 1);
        dateHint.textContent = billedDays + (billedDays === 1 ? " day" : " days");

        if (isNaN(rate)) {
            estimate.textContent = "Pick a car to see the cost.";
        } else {
            estimate.innerHTML = "Estimated cost: <strong>" + formatMoney(billedDays * rate) +
                "</strong> (" + billedDays + (billedDays === 1 ? " day" : " days") +
                " at " + formatMoney(rate) + " a day)";
        }
    }

    [car, start, end].forEach(function (field) {
        field.addEventListener("change", update);
        field.addEventListener("input", update);
    });
    update();
}

/* ---------------------------------------------------------------------
   4. Record Payment form: fill in the balance owed for the chosen rental
   ------------------------------------------------------------------- */
function setUpPaymentForm() {
    var form = document.getElementById("payment-form");
    if (!form) return;

    var rental = form.querySelector("#rental_id");
    var amount = form.querySelector("#amount");
    var hint = form.querySelector("#balance-hint");

    function update(fillAmount) {
        var option = rental.options[rental.selectedIndex];
        var balance = option ? parseFloat(option.dataset.balance) : NaN;

        if (isNaN(balance)) {
            hint.textContent = "";
            amount.removeAttribute("max");
            return;
        }

        amount.max = balance.toFixed(2);
        hint.textContent = "Balance owed on this rental: " + formatMoney(balance);
        if (fillAmount) {
            amount.value = balance.toFixed(2);
        }
    }

    rental.addEventListener("change", function () { update(true); });
    // On page load, only fill the amount if the field is still empty
    update(amount.value === "");
}
