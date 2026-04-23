export default async function handler(req, res) {

  res.setHeader("Access-Control-Allow-Origin", "*");
  res.setHeader("Access-Control-Allow-Methods", "POST, OPTIONS");
  res.setHeader("Access-Control-Allow-Headers", "Content-Type");

  if (req.method === "OPTIONS") {
    return res.status(200).end();
  }

  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method not allowed" });
  }

  const { phone } = req.body;

  try {
    const response = await fetch(
      "https://www.myntra.com/gateway/auth/v1/forgetpassword",
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-myntraweb": "Yes",
          "X-Requested-With": "browser"
        },
        body: JSON.stringify({ phoneNumber: phone })
      }
    );

    const statusCode = response.status;
    const text = await response.text();

    let data = {};
    try {
      data = JSON.parse(text);
    } catch {}

    let status = "registered"; // default

    const msg = (data.message || "").toLowerCase();

    // ✅ Strong detection
    if (
      msg.includes("does not exist") ||
      msg.includes("not found") ||
      statusCode === 404
    ) {
      status = "fresh";
    }

    // 🔴 Known registered messages
    else if (
      msg.includes("recover account") ||
      msg.includes("does not have email") ||
      msg.includes("otp") ||
      statusCode === 200
    ) {
      status = "registered";
    }

    // ⚠️ Fallback: unknown → treat as registered
    else {
      status = "registered";
    }

    return res.json({
      status,
      debug: {
        statusCode,
        message: data.message || null
      }
    });

  } catch (error) {
    return res.status(500).json({ error: "Server error" });
  }
}
