exports.handler = async (event, context) => {
    // Sirf POST request allow karein
    if (event.httpMethod !== 'POST') {
        return { statusCode: 405, body: 'Method Not Allowed' };
    }

    try {
        const data = JSON.parse(event.body);

        if (data.action !== 'create_order') {
            return { statusCode: 400, body: JSON.stringify({ error: "Invalid Action Request" }) };
        }

        // APNI CASHFREE KEYS YAHA DAALEIN 👇
        const appId = '6985583cfdb99d02be7d5592a4855896';
        const secretKey = 'cfsk_ma_prod_0cc076c7389a82fb89666c709ed4cf6a_e9933647';
        const environment = 'sandbox'; // Live hone par 'production' karein

        const baseUrl = environment === 'sandbox' 
            ? "https://sandbox.cashfree.com/pg/orders" 
            : "https://api.cashfree.com/pg/orders";

        // Unique Order ID banayein
        const orderId = "ORDER_" + data.noteId.replace(/[^a-zA-Z0-9]/g, '').substring(0, 10) + "_" + Date.now();

        const payload = {
            order_id: orderId,
            order_amount: parseFloat(data.price),
            order_currency: "INR",
            customer_details: {
                customer_id: data.userId.replace(/[^a-zA-Z0-9_-]/g, '').substring(0, 50),
                customer_name: data.customerName || "Student",
                customer_email: data.customerEmail || "student@example.com",
                customer_phone: "9999999999"
            },
            order_meta: {
                return_url: "https://noteslele.netlify.app/"
            },
            order_note: "Payment for Notes ID: " + data.noteId
        };

        // Cashfree API call
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: {
                "Content-Type": "application/json",
                "x-client-id": appId,
                "x-client-secret": secretKey,
                "x-api-version": "2023-08-01"
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok && result.payment_session_id) {
            return {
                statusCode: 200,
                body: JSON.stringify({
                    payment_session_id: result.payment_session_id,
                    order_id: orderId
                })
            };
        } else {
            return {
                statusCode: response.status,
                body: JSON.stringify({ error: "Cashfree Order Creation Failed", details: result })
            };
        }
    } catch (error) {
        return {
            statusCode: 500,
            body: JSON.stringify({ error: error.message })
        };
    }
};
