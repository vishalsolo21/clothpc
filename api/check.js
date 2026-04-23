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

    let data = {};
    try {
      data = await response.json();
    } catch {
      data = {};
    }

    let status = "registered"; // default

    if (data.message) {
      const msg = data.message.toLowerCase();

      if (msg.includes("account does not exist")) {
        status = "fresh";
      } else {
        status = "registered";
      }
    } else {
      // blank response = registered
      status = "registered";
    }

    return res.json({ status });

  } catch (error) {
    return res.status(500).json({ error: "Server error" });
  }
}
