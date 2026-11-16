

document.addEventListener('DOMContentLoaded', function () {
    initSeatSelection();
    initGroupSeatFinder();
    initMpesaHelper();
    initCashoutModal();
});


function initSeatSelection() {
    const seatButtons = document.querySelectorAll('.seat.available');
    const selectedSeatInput = document.getElementById('selected_seat');
    const selectedSeatDisplay = document.getElementById('display_selected_seat');
    const checkoutBtn = document.getElementById('btn_proceed_checkout');

    seatButtons.forEach(seat => {
        seat.addEventListener('click', function () {
            const seatNum = this.getAttribute('data-seat');

            
            document.querySelectorAll('.seat.selected').forEach(s => {
                s.classList.remove('selected');
            });

            
            this.classList.add('selected');

           
            if (selectedSeatInput) {
                selectedSeatInput.value = seatNum;
            }
            if (selectedSeatDisplay) {
                selectedSeatDisplay.textContent = 'Seat #' + seatNum;
            }
            if (checkoutBtn) {
                checkoutBtn.removeAttribute('disabled');
                checkoutBtn.classList.remove('btn-disabled');
            }
        });
    });

    
    document.querySelectorAll('.seat.occupied').forEach(seat => {
        seat.addEventListener('click', function () {
            const seatNum = this.getAttribute('data-seat');
            alert(`Seat #${seatNum} is already booked and confirmed. Please select a green available seat.`);
        });
    });
}


function initGroupSeatFinder() {
    const findGroupBtn = document.getElementById('btn_find_group');
    if (!findGroupBtn) return;

    findGroupBtn.addEventListener('click', function () {
        const groupSize = parseInt(document.getElementById('group_size_select').value, 10);
        const allSeats = document.querySelectorAll('.seat');
        const occupiedSet = new Set();

        allSeats.forEach(seat => {
            if (seat.classList.contains('occupied')) {
                occupiedSet.add(parseInt(seat.getAttribute('data-seat'), 10));
            }
        });

       
        let foundSeats = null;

        if (groupSize === 2) {
            for (let row = 1; row <= 8; row++) {
                const leftA = (row - 1) * 4 + 1;
                const leftB = (row - 1) * 4 + 2;
                if (!occupiedSet.has(leftA) && !occupiedSet.has(leftB)) {
                    foundSeats = [leftA, leftB];
                    break;
                }
                const rightC = (row - 1) * 4 + 3;
                const rightD = (row - 1) * 4 + 4;
                if (!occupiedSet.has(rightC) && !occupiedSet.has(rightD)) {
                    foundSeats = [rightC, rightD];
                    break;
                }
            }
        } else if (groupSize === 3 || groupSize === 4) {
           
            for (let row = 1; row <= 8; row++) {
                const s1 = (row - 1) * 4 + 1;
                const s2 = (row - 1) * 4 + 2;
                const s3 = (row - 1) * 4 + 3;
                const s4 = (row - 1) * 4 + 4;
                const rowSeats = [s1, s2, s3, s4];
                const availableInRow = rowSeats.filter(s => !occupiedSet.has(s));

                if (availableInRow.length >= groupSize) {
                    foundSeats = availableInRow.slice(0, groupSize);
                    break;
                }
            }
        }

        if (foundSeats && foundSeats.length > 0) {
           
            document.querySelectorAll('.seat.selected').forEach(s => s.classList.remove('selected'));

           
            foundSeats.forEach((seatNum, idx) => {
                const seatEl = document.querySelector(`.seat[data-seat='${seatNum}']`);
                if (seatEl) {
                    seatEl.classList.add('selected');
                    
                    if (idx === 0) {
                        const selectedSeatInput = document.getElementById('selected_seat');
                        const selectedSeatDisplay = document.getElementById('display_selected_seat');
                        const checkoutBtn = document.getElementById('btn_proceed_checkout');
                        if (selectedSeatInput) selectedSeatInput.value = seatNum;
                        if (selectedSeatDisplay) selectedSeatDisplay.textContent = 'Seat #' + seatNum + ' (Group of ' + foundSeats.length + ')';
                        if (checkoutBtn) {
                            checkoutBtn.removeAttribute('disabled');
                            checkoutBtn.classList.remove('btn-disabled');
                        }
                    }
                }
            });

            const statusBox = document.getElementById('group_finder_status');
            if (statusBox) {
                statusBox.innerHTML = `<span style="color:var(--success);font-weight:700;">✓ Found ${foundSeats.length} adjacent seats: Seats ${foundSeats.join(' & ')}!</span>`;
            }
        } else {
            alert(`No contiguous ${groupSize} seats are available in the same row on this trip. Try selecting another trip or individual seats.`);
        }
    });
}


function initMpesaHelper() {
    const generateBtn = document.getElementById('btn_generate_mpesa');
    const mpesaInput = document.getElementById('mpesa_code');
    if (!generateBtn || !mpesaInput) return;

    generateBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = 'Q';
        for (let i = 0; i < 9; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        mpesaInput.value = code;
        mpesaInput.style.borderColor = 'var(--success)';
    });
}


function initCashoutModal() {
    const openBtns = document.querySelectorAll('.btn-open-cashout');
    const modal = document.getElementById('cashout_modal');
    const closeBtn = document.querySelector('.close-modal-btn');
    const cancelModalBtn = document.getElementById('btn_close_modal');

    if (!modal) return;

    openBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            const bookingId = this.getAttribute('data-booking-id');
            const bookingRef = this.getAttribute('data-ref');
            const fare = parseFloat(this.getAttribute('data-fare'));
            const phone = this.getAttribute('data-phone');

            const fee = Math.round(fare * 0.10 * 100) / 100;
            const refund = Math.round((fare - fee) * 100) / 100;

            document.getElementById('modal_booking_id').value = bookingId;
            document.getElementById('modal_ref_display').textContent = bookingRef;
            document.getElementById('modal_orig_fare').textContent = 'KES ' + fare.toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('modal_fee').textContent = 'KES ' + fee.toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('modal_refund').textContent = 'KES ' + refund.toLocaleString('en-US', { minimumFractionDigits: 2 });
            document.getElementById('modal_phone').value = phone;

            modal.classList.add('active');
        });
    });

    const closeModal = () => modal.classList.remove('active');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

    window.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}
