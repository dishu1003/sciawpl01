<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✅ Success - Thank You!</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2/?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        
        .success-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 90%;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00d2d3, #54a0ff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            margin: 0 auto 30px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
        
        .success-title {
            font-size: 36px;
            font-weight: 800;
            color: #333;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .success-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .next-steps {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .next-steps h3 {
            font-size: 20px;
            font-weight: 700;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .next-steps ul {
            list-style: none;
            padding: 0;
        }
        
        .next-steps li {
            padding: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
        }
        
        .next-steps li i {
            color: #00d2d3;
            font-size: 16px;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #555;
            border: 2px solid #e9ecef;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .social-share {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #f1f3f4;
        }
        
        .social-share h4 {
            font-size: 16px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .social-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: transform 0.3s ease;
        }
        
        .social-btn:hover {
            transform: scale(1.1);
        }
        
        .social-btn.whatsapp {
            background: #25d366;
        }
        
        .social-btn.facebook {
            background: #1877f2;
        }
        
        .social-btn.twitter {
            background: #1da1f2;
        }
        
        .social-btn.instagram {
            background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%);
        }
        
        @media (max-width: 768px) {
            .success-container {
                padding: 40px 20px;
            }
            
            .success-title {
                font-size: 28px;
            }
            
            .success-message {
                font-size: 16px;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1 class="success-title" data-en="Thank You!" data-hi="धन्यवाद!">धन्यवाद!</h1>
        
        <p class="success-message" data-en="Your information has been successfully submitted. Our team will contact you soon to discuss the exciting business opportunity!" data-hi="आपकी जानकारी सफलतापूर्वक जमा कर दी गई है। हमारी टीम जल्द ही आपसे संपर्क करेगी ताकि हम इस रोमांचक व्यापारिक अवसर के बारे में चर्चा कर सकें!">आपकी जानकारी सफलतापूर्वक जमा कर दी गई है। हमारी टीम जल्द ही आपसे संपर्क करेगी ताकि हम इस रोमांचक व्यापारिक अवसर के बारे में चर्चा कर सकें!</p>
        
        <div class="next-steps">
            <h3>
                <i class="fas fa-list-check"></i>
                <span data-en="What happens next?" data-hi="अब क्या होगा?">अब क्या होगा?</span>
            </h3>
            <ul>
                <li>
                    <i class="fas fa-phone"></i>
                    <span data-en="Our team member will call you within 24 hours" data-hi="हमारा टीम सदस्य 24 घंटे के भीतर आपको कॉल करेगा">हमारा टीम सदस्य 24 घंटे के भीतर आपको कॉल करेगा</span>
                </li>
                <li>
                    <i class="fas fa-handshake"></i>
                    <span data-en="We'll discuss the business opportunity in detail" data-hi="हम व्यापारिक अवसर के बारे में विस्तार से चर्चा करेंगे">हम व्यापारिक अवसर के बारे में विस्तार से चर्चा करेंगे</span>
                </li>
                <li>
                    <i class="fas fa-chart-line"></i>
                    <span data-en="You'll learn about income potential and growth" data-hi="आप आय की संभावना और विकास के बारे में जानेंगे">आप आय की संभावना और विकास के बारे में जानेंगे</span>
                </li>
                <li>
                    <i class="fas fa-rocket"></i>
                    <span data-en="Get ready to start your journey to success!" data-hi="सफलता की यात्रा शुरू करने के लिए तैयार हो जाइए!">सफलता की यात्रा शुरू करने के लिए तैयार हो जाइए!</span>
                </li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="/" class="btn btn-primary">
                <i class="fas fa-home"></i>
                <span data-en="Back to Home" data-hi="होम पर वापस जाएं">होम पर वापस जाएं</span>
            </a>
            <a href="/team/" class="btn btn-secondary">
                <i class="fas fa-users"></i>
                <span data-en="Join Our Team" data-hi="हमारी टीम में शामिल हों">हमारी टीम में शामिल हों</span>
            </a>
        </div>
        
        <div class="social-share">
            <h4 data-en="Share this opportunity with your friends!" data-hi="इस अवसर को अपने दोस्तों के साथ साझा करें!">इस अवसर को अपने दोस्तों के साथ साझा करें!</h4>
            <div class="social-buttons">
                <a href="https://wa.me/?text=Check%20out%20this%20amazing%20business%20opportunity!" class="social-btn whatsapp" target="_blank">
                    <i class="fab fa-whatsapp"></i>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=" class="social-btn facebook" target="_blank">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?text=Check%20out%20this%20amazing%20business%20opportunity!" class="social-btn twitter" target="_blank">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="https://www.instagram.com/" class="social-btn instagram" target="_blank">
                    <i class="fab fa-instagram"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Language switching functionality
        function switchLanguage(lang) {
            document.querySelectorAll('[data-en][data-hi]').forEach(element => {
                element.textContent = element.getAttribute('data-' + lang);
            });

            // Update document direction for Hindi
            if (lang === 'hi') {
                document.documentElement.setAttribute('dir', 'ltr');
            } else {
                document.documentElement.removeAttribute('dir');
            }
        }

        // Auto-redirect after 30 seconds
        setTimeout(function() {
            if (confirm('Would you like to return to the home page?')) {
                window.location.href = '/';
            }
        }, 30000);

        // Add language toggle
        const langToggle = document.createElement('div');
        langToggle.className = 'lang-toggle';
        langToggle.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 1000;
        `;
        
        const enBtn = document.createElement('button');
        enBtn.textContent = 'EN';
        enBtn.style.cssText = `
            padding: 8px 16px;
            border: 2px solid rgba(255,255,255,0.3);
            background: rgba(255,255,255,0.1);
            color: white;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        `;
        enBtn.onclick = () => switchLanguage('en');
        
        const hiBtn = document.createElement('button');
        hiBtn.textContent = 'हिं';
        hiBtn.style.cssText = enBtn.style.cssText + 'background: rgba(255,255,255,0.2);';
        hiBtn.onclick = () => switchLanguage('hi');
        
        langToggle.appendChild(enBtn);
        langToggle.appendChild(hiBtn);
        document.body.appendChild(langToggle);
        
        // Initialize with Hindi
        switchLanguage('hi');
    </script>
</body>
</html>