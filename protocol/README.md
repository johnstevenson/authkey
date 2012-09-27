= Auth-Key =

Auth-Key is an API authentication scheme that is designed to be simple and flexible. It is based on services like Amazon S3 and Azure and uses similar procedures and terminology.

== Basic principles ==

When making an HTTP request, two additional HTTP header are sent: one is the authentication header that contains authentication details of the sender (client), while the other is a [[Timestamp|Timestamp]]. The server then uses these details to decide whether to allow the request or not.

To make this work, the client must have been issued with an **[[AccountId|AccountId]]** and a **[[SecretKey|SecretKey]]** which it uses to construct the authentication header. This header contains the AccountId and a **[[Signature|Signature]]** of other header elements, computed by using the SecretKey.

On receiving the request, the server looks up the AccountId to obtain its own copy of the SecretKey, computes a signature using the same header elements then compares this with the one sent in the header. If they are the same the server assumes that the sender has access to the SecretKey. If the Timestamp from the header is within an allowed period, the server can process the request with the authority designated to the AccountId owner.

== Authentication header ==
The format of the authentication header is:

{{{
<HeaderName>: <SchemeName> <AccountId>:<Signature>
}}}
\\
Where, using the default values, **[[HeaderName|HeaderName]] **is //Auth-Key// and **[[SchemeName|SchemeName]]** is //MAC//. For example:

{{{
Auth-Key: MAC example-id:RbucvcyvXcwRroH7h7OIWQy2AwJVu6pCGmN/kWdwZ48=
}}}
\\
Note that the standard HTTP Authorization header is not used because some HTTP server
libraries do not expose it. Implementations are free to use any HeaderName they wish, and likewise with the SchemeName (for example, Amazon S3 uses //AWS// while Azure uses either //SharedKey// or //SharedKeyLite//). In pseudo-code, using the default values, the header is formatted as follows:
{{{
"Auth-Key" + ":" + "MAC" + " " + AccountId + ":" + Signature
}}}
== Other Headers ==
This scheme makes use of **[[XHeaders|X-Headers]]**, which are extra headers included with the request that start with an x-//[identifier]//- prefix, where //identifier// often identifies the service provider. For example, Amazon uses the prefix //x-amz-//, while Azure uses //x-ms-//. The Auth-Key scheme defaults to "mac" (making the prefix **x-mac-**) but allows implementations to use any name they wish.

For simplicity this scheme only uses the X-Headers, rather than standard headers, for computing the Signature and only requires one to be present: the **[[XMacDate|x-mac-date]]** header, which is a RFC1123 formatted UTC timestamp. This MUST be included, in addition to the standard HTTP Date header which takes a similar form.

Other standard headers that an implementation wishes to be "signed" must be included as X-Headers, for example //x-mac-content-type//, //x-mac-content-md5//. Implementations may set their own rules regarding which X-Headers must be sent. However it is a requirement that all X-Headers in the request MUST be signed, regardless of whether they are required or not.

== Creating the signature ==
The Signature is created by applying the HMAC-SHA-256 function to a string of concatenated header elements, known as the [[StringToSign|StringToSign]], using a key comprising the SHA-256 hash of the SecretKey and the Timestamp, known as the [[SigningKey|SigningKey]], then Base-64 encoding the result.

{{{
Signature = Base64( HMAC-SHA-256( StringToSign, SigningKey ) )

SigningKey = HASH-SHA-256( Timestamp + SecretKey ) // Timestamp is the x-mac-date value

// for requests:
StringToSign = HTTP-Verb + <LF> +
    CanonicalizedX-Headers + <LF> +
    URI-Path + <LF> +
    URI-Query

// for responses:
StringToSign = <LF> +
    CanonicalizedX-Headers + <LF> +
    <LF> +
    <LF>

// <LF> is the newline character ("\n") - Ascii 0x0A, Unicode U+000A
}}}

\\
Please see the [[StringToSign|StringToSign]] section for more details.

\\
----

== Pages ==
*[[AccountId|AccountId]]
*[[SecretKey|SecretKey]]
*[[Signature|Signature]]
*[[XHeaders|X-Headers]]
*[[XMacDate|Date header]]


