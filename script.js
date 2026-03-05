// BillPro - Sales Billing System
// Interactive JavaScript Features

document.addEventListener('DOMContentLoaded', function() {
    // Smooth scroll for all internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // Add loading state to form submissions
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.6';
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '⏳ Processing...';
                
                // Re-enable after 3 seconds as fallback
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.innerHTML = originalText;
                }, 3000);
            }
        });
    });

    // Confirm before deleting
    const deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Number formatting for currency inputs
    const currencyInputs = document.querySelectorAll('input[type="number"][step]');
    currencyInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });

    // Mobile sidebar toggle
    const createSidebarToggle = () => {
        if (window.innerWidth <= 768) {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebar && !document.querySelector('.sidebar-toggle')) {
                const toggle = document.createElement('button');
                toggle.className = 'sidebar-toggle';
                toggle.innerHTML = '☰';
                toggle.style.cssText = `
                    position: fixed;
                    top: 20px;
                    left: 20px;
                    z-index: 1000;
                    background: var(--primary);
                    color: white;
                    border: none;
                    width: 40px;
                    height: 40px;
                    border-radius: 8px;
                    font-size: 20px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                `;
                
                toggle.addEventListener('click', () => {
                    sidebar.style.transform = sidebar.style.transform === 'translateX(0px)' 
                        ? 'translateX(-100%)' 
                        : 'translateX(0px)';
                });
                
                document.body.appendChild(toggle);
            }
        }
    };
    
    createSidebarToggle();
    window.addEventListener('resize', createSidebarToggle);

    // Print functionality
    window.printInvoice = function() {
        window.print();
    };

    // Format numbers to Indian currency format
    window.formatCurrency = function(amount) {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: 'INR',
            minimumFractionDigits: 2
        }).format(amount);
    };

    // Calculate GST amounts
    window.calculateGST = function(amount, gstRate, isIGST = false) {
        const gstAmount = (amount * gstRate) / 100;
        if (isIGST) {
            return {
                igst: gstAmount,
                cgst: 0,
                sgst: 0,
                total: amount + gstAmount
            };
        } else {
            return {
                igst: 0,
                cgst: gstAmount / 2,
                sgst: gstAmount / 2,
                total: amount + gstAmount
            };
        }
    };

    // Add fade-in animation to stat cards
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.stat-card, .action-card').forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease-out';
        observer.observe(card);
    });

    console.log('BillPro System Loaded Successfully! 🚀');
});
