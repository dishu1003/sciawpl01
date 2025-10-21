<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Thank You - SpartanCommunityIndia</title>
  <style>
    :root{
      --bg:#0c0f1a; --card:#0f1424; --muted:#b9c2d0; --brand:#6c7bff; --brand2:#9b5cff; --accent:#23d5ab;
      --white:#fff; --shadow:0 40px 90px rgba(0,0,0,.35); --radius:16px;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: radial-gradient(1200px 600px at 20% -10%, #1a2150 0%, transparent 60%), linear-gradient(180deg, #0b0f1c 0%, #06070d 100%);
      color:var(--white); min-height:100vh; display:flex; align-items:center; justify-content:center;
    }
    .container{max-width:600px; margin:0 auto; padding:20px; text-align:center}
    .success-icon{
      width:100px; height:100px; background:linear-gradient(135deg, var(--accent), var(--brand));
      border-radius:50%; display:flex; align-items:center; justify-content:center;
      margin:0 auto 30px; font-size:48px; animation: pulse 2s infinite;
    }
    @keyframes pulse{
      0%, 100%{transform:scale(1);}
      50%{transform:scale(1.05);}
    }
    h1{font-size:42px; margin:0 0 20px; font-weight:800; background:linear-gradient(135deg, var(--brand), var(--accent)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text}
    .subtitle{font-size:20px; color:var(--muted); margin-bottom:30px}
    .card{
      background:linear-gradient(180deg, #0e1433, #0b0f22); border:1px solid #1b2552;
      border-radius:16px; padding:30px; margin:30px 0; text-align:left;
    }
    .step{
      display:flex; gap:15px; align-items:flex-start; margin:20px 0; padding:15px;
      background:rgba(108,123,255,.05); border-radius:10px; border-left:4px solid var(--brand);
    }
    .step-number{
      width:30px; height:30px; background:var(--brand); color:#fff; border-radius:50%;
      display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0;
    }
    .step-content h3{margin:0 0 8px; font-size:16px; color:var(--white)}
    .step-content p{margin:0; color:var(--muted); font-size:14px; line-height:1.5}
    .btn{
      display:inline-flex; align-items:center; gap:10px; padding:14px 22px; border-radius:12px; font-weight:800; letter-spacing:.2px; cursor:pointer;
      border:0; background:linear-gradient(135deg, var(--brand) 0%, var(--brand2) 100%); color:#fff; box-shadow:0 10px 30px rgba(108,123,255,.35);
      transition:transform .15s ease, box-shadow .2s ease; text-decoration:none; margin:10px;
    }
    .btn:hover{transform:translateY(-2px); box-shadow:0 14px 40px rgba(108,123,255,.45)}
    .btn.secondary{background:#121a35; border:1px solid #24305a; box-shadow:none}
    .contact-info{
      background:linear-gradient(135deg, rgba(35,213,171,.1), rgba(108,123,255,.1));
      border:1px solid #23306b; border-radius:12px; padding:20px; margin:20px 0;
    }
    .contact-info h3{margin:0 0 15px; color:var(--accent); font-size:18px}
    .contact-item{display:flex; align-items:center; gap:10px; margin:10px 0; color:var(--muted)}
    .contact-item strong{color:var(--white)}
    .footer{color:var(--muted); font-size:14px; margin-top:40px}
  </style>
</head>
<body>
  <div class="container">
    <div class="success-icon">🎉</div>
    
    <h1>Application Submitted Successfully!</h1>
    <p class="subtitle">Thank you for your interest in SpartanCommunityIndia</p>
    
    <div class="card">
      <h3 style="margin-top:0; color:var(--accent); font-size:20px">What Happens Next?</h3>
      
      <div class="step">
        <div class="step-number">1</div>
        <div class="step-content">
          <h3>Application Review</h3>
          <p>Our team will review your application within 24 hours to ensure you're a good fit for our program.</p>
        </div>
      </div>
      
      <div class="step">
        <div class="step-number">2</div>
        <div class="step-content">
          <h3>Personalized Contact</h3>
          <p>We'll reach out to you via your preferred contact method to discuss your goals and answer any questions.</p>
        </div>
      </div>
      
      <div class="step">
        <div class="step-number">3</div>
        <div class="step-content">
          <h3>Program Details</h3>
          <p>If you're accepted, we'll share detailed program information, pricing, and next steps.</p>
        </div>
      </div>
      
      <div class="step">
        <div class="step-number">4</div>
        <div class="step-content">
          <h3>Begin Your Journey</h3>
          <p>Join our elite community and start your transformation journey with personalized support.</p>
        </div>
      </div>
    </div>
    
    <div class="contact-info">
      <h3>📞 Need Immediate Help?</h3>
      <div class="contact-item">
        <strong>WhatsApp:</strong> +91 98765 43210
      </div>
      <div class="contact-item">
        <strong>Email:</strong> hello@spartancommunityindia.com
      </div>
      <div class="contact-item">
        <strong>Response Time:</strong> Usually within 2-4 hours
      </div>
    </div>
    
    <div style="margin:30px 0">
      <a href="index.html" class="btn">← Back to Home</a>
      <a href="#" class="btn secondary" onclick="window.print()">📄 Print This Page</a>
    </div>
    
    <div class="footer">
      <p>© 2024 SpartanCommunityIndia. All rights reserved.</p>
      <p>Your application reference will be sent to your email shortly.</p>
    </div>
  </div>

  <script>
    // Store completion data
    const applicationData = JSON.parse(localStorage.getItem('completeApplicationData') || '{}');
    if (Object.keys(applicationData).length > 0) {
      console.log('Application completed:', applicationData);
      
      // You can send this data to your server for processing
      // fetch('/api/save-application', {
      //   method: 'POST',
      //   headers: {'Content-Type': 'application/json'},
      //   body: JSON.stringify(applicationData)
      // });
    }
    
    // Clear form data after successful submission
    setTimeout(() => {
      localStorage.removeItem('formA');
      localStorage.removeItem('formB');
      localStorage.removeItem('formC');
      localStorage.removeItem('formD');
      localStorage.removeItem('combinedFormData');
      localStorage.removeItem('completeApplicationData');
    }, 5000);
  </script>
</body>
</html>