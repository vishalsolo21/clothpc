export default async function handler(req, res) {

  // CORS fix
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

    const text = await response.text();

    let data = {};
    try {
      data = JSON.parse(text);
    } catch {}

    let status = "registered";

    if (data.message) {
      const msg = data.message.toLowerCase();

      if (msg.includes("account does not exist")) {
        status = "fresh";
      }
    }

    return res.json({ status });

  } catch (error) {
    return res.status(500).json({ error: "Server error" });
  }
}
