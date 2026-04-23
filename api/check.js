export default async function handler(req, res) {
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

    const data = await response.json();

    // Logic based on response
    if (data.message && data.message.includes("recover account")) {
      return res.json({ status: "registered" });
    } else {
      return res.json({ status: "not_registered" });
    }

  } catch (error) {
    return res.status(500).json({ error: "Server error" });
  }
}
