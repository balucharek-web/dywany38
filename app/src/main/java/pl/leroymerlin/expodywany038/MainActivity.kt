@file:OptIn(androidx.compose.material3.ExperimentalMaterial3Api::class)
package pl.leroymerlin.expodywany038

import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.Search
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.credentials.CredentialManager
import androidx.credentials.GetCredentialRequest
import androidx.credentials.GetCredentialResponse
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GetGoogleIdOption
import com.google.firebase.auth.FirebaseAuth
import com.google.firebase.auth.FirebaseUser
import com.google.firebase.auth.GoogleAuthProvider
import com.google.firebase.firestore.FirebaseFirestore
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch
import java.util.concurrent.Executors

data class Product(
    val id: String = "",
    val location: Int = 0,
    val sub: String = "",
    val name: String = "Nowy Produkt",
    val reference: String = "",
    val ean: String = "",
    val updatedAt: Long = 0L
)

class InventoryViewModel : ViewModel() {
    private val db = FirebaseFirestore.getInstance()
    private val auth = FirebaseAuth.getInstance()
    private val collectionRef = db.collection("store_038").document("products").collection("items")

    private val _products = MutableStateFlow<List<Product>>(emptyList())
    val products: StateFlow<List<Product>> = _products

    private val _currentUser = MutableStateFlow(auth.currentUser)
    val currentUser: StateFlow<FirebaseUser?> = _currentUser

    init {
        collectionRef.addSnapshotListener { snapshot, error ->
            if (error != null) return@addSnapshotListener
            if (snapshot != null) {
                val updatedList = snapshot.documents.mapNotNull { doc ->
                    doc.toObject(Product::class.java)?.copy(id = doc.id)
                }
                _products.value = updatedList.sortedBy { it.location }
            }
        }
    }

    fun saveProduct(ean: String, location: Int, sub: String) {
        if (auth.currentUser == null || ean.isBlank()) return
        
        val product = Product(
            id = ean,
            ean = ean,
            location = location,
            sub = sub,
            updatedAt = System.currentTimeMillis()
        )
        collectionRef.document(ean).set(product)
    }

    fun signInWithGoogleCredential(response: GetCredentialResponse, onSuccess: () -> Unit, onError: (Exception) -> Unit) {
        viewModelScope.launch {
            try {
                val credential = response.credential
                if (credential is androidx.credentials.CustomCredential &&
                    credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL
                ) {
                    val googleIdTokenCredential = GoogleIdTokenCredential.createFrom(credential.data)
                    val googleToken = googleIdTokenCredential.idToken
                    val firebaseCred = GoogleAuthProvider.getCredential(googleToken, null)
                    
                    auth.signInWithCredential(firebaseCred)
                        .addOnSuccessListener { authResult ->
                            _currentUser.value = authResult.user
                            onSuccess()
                        }
                        .addOnFailureListener { e -> onError(e) }
                } else {
                    onError(Exception("Nieznany typ poświadczenia"))
                }
            } catch (e: Exception) {
                onError(e)
            }
        }
    }

    fun logout() {
        auth.signOut()
        _currentUser.value = null
    }
}

class MainActivity : ComponentActivity() {
    private val viewModel = InventoryViewModel()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    MainScreen(viewModel)
                }
            }
        }
    }
}

@Composable
fun MainScreen(viewModel: InventoryViewModel) {
    var showScanner by remember { mutableStateOf(false) }
    var scannedEan by remember { mutableStateOf<String?>(null) }
    var showManualDialog by remember { mutableStateOf(false) }
    var showLoginDialog by remember { mutableStateOf(false) }
    
    val currentUser by viewModel.currentUser.collectAsState()
    val context = LocalContext.current
    val coroutineScope = rememberCoroutineScope()
    
    var hasCamPermission by remember {
        mutableStateOf(ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED)
    }
    
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        hasCamPermission = granted
    }

    fun launchGoogleLogin() {
        coroutineScope.launch {
            try {
                val googleIdOption = GetGoogleIdOption.Builder()
                    .setFilterByAuthorizedAccounts(false)
                    .setAutoSelectEnabled(false)
                    .build()

                val request = GetCredentialRequest.Builder()
                    .addCredentialOption(googleIdOption)
                    .build()

                val credentialManager = CredentialManager.create(context)
                val result = credentialManager.getCredential(context, request)
                
                viewModel.signInWithGoogleCredential(
                    response = result,
                    onSuccess = { showLoginDialog = false },
                    onError = { err -> Log.e("Login", "Błąd logowania", err) }
                )
            } catch (e: androidx.credentials.exceptions.GetCredentialCancellationException) {
                // Użytkownik anulował okno logowania
            } catch (e: Exception) {
                Log.e("Login", "Błąd menedżera poświadczeń", e)
            }
        }
    }

    if (scannedEan != null) {
        if (currentUser == null) {
            showLoginDialog = true
        } else {
            SaveProductDialog(
                initialEan = scannedEan!!,
                onDismiss = { scannedEan = null },
                onSave = { ean, loc, sub ->
                    viewModel.saveProduct(ean, loc, sub)
                    scannedEan = null
                }
            )
        }
    }

    if (showManualDialog) {
        if (currentUser == null) {
            showLoginDialog = true
            showManualDialog = false
        } else {
            SaveProductDialog(
                initialEan = "",
                onDismiss = { showManualDialog = false },
                onSave = { ean, loc, sub ->
                    viewModel.saveProduct(ean, loc, sub)
                    showManualDialog = false
                }
            )
        }
    }

    if (showLoginDialog) {
        AlertDialog(
            onDismissRequest = { showLoginDialog = false; scannedEan = null },
            title = { Text("Wymagane logowanie służbowe") },
            text = { Text("Aby dodawać lub edytować dywany, wybierz swoje konto Google (w tym profil służbowy Leroy Merlin).") },
            confirmButton = {
                Button(onClick = {
                    launchGoogleLogin()
                }) {
                    Text("Zaloguj kontem Google")
                }
            },
            dismissButton = {
                TextButton(onClick = { showLoginDialog = false; scannedEan = null }) {
                    Text("Anuluj")
                }
            }
        )
    }

    if (showScanner) {
        if (hasCamPermission) {
            CameraScannerView(
                onBarcodeScanned = { ean ->
                    scannedEan = ean
                    showScanner = false
                },
                onClose = { showScanner = false }
            )
        } else {
            Column(modifier = Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
                Text("Brak uprawnień do aparatu")
                Button(onClick = { launcher.launch(Manifest.permission.CAMERA) }) {
                    Text("Nadaj uprawnienia")
                }
                Button(onClick = { showScanner = false }) { Text("Wróć") }
            }
        }
    } else {
        Scaffold(
            topBar = {
                TopAppBar(
                    title = { Text("Expo Dywany - Sklep 038") },
                    actions = {
                        IconButton(onClick = {
                            if (currentUser == null) {
                                showLoginDialog = true
                            } else {
                                viewModel.logout()
                            }
                        }) {
                            Icon(
                                imageVector = if (currentUser != null) Icons.Filled.Person else Icons.Filled.Lock,
                                contentDescription = "Profil / Logowanie"
                            )
                        }
                    }
                )
            },
            floatingActionButton = {
                FloatingActionButton(onClick = {
                    if (currentUser == null) {
                        showLoginDialog = true
                    } else {
                        showManualDialog = true
                    }
                }) {
                    Icon(Icons.Filled.Add, contentDescription = "Dodaj produkt")
                }
            }
        ) { padding ->
            ProductList(
                viewModel = viewModel,
                modifier = Modifier.padding(padding),
                onStartScan = {
                    if (!hasCamPermission) launcher.launch(Manifest.permission.CAMERA)
                    showScanner = true
                }
            )
        }
    }
}

@Composable
fun ProductList(viewModel: InventoryViewModel, modifier: Modifier = Modifier, onStartScan: () -> Unit) {
    val products by viewModel.products.collectAsState()
    var searchQuery by remember { mutableStateOf("") }

    val filteredProducts = products.filter {
        it.ean.contains(searchQuery, ignoreCase = true) ||
        it.location.toString().contains(searchQuery) ||
        it.sub.contains(searchQuery, ignoreCase = true)
    }

    Column(modifier = modifier.padding(16.dp)) {
        OutlinedTextField(
            value = searchQuery,
            onValueChange = { searchQuery = it },
            modifier = Modifier.fillMaxWidth(),
            placeholder = { Text("Szukaj EAN / regału (dostępne bez logowania)...") },
            leadingIcon = { Icon(Icons.Filled.Search, contentDescription = "Szukaj") },
            trailingIcon = {
                IconButton(onClick = onStartScan) {
                    Icon(Icons.Filled.CameraAlt, contentDescription = "Skanuj kod")
                }
            },
            singleLine = true
        )
        
        Spacer(modifier = Modifier.height(16.dp))

        if (products.isEmpty()) {
            Text("Brak produktów w bazie...")
        } else if (filteredProducts.isEmpty()) {
            Text("Brak wyników pasujących do wyszukiwania.")
        } else {
            LazyColumn {
                items(filteredProducts) { product ->
                    Card(modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
                        Column(modifier = Modifier.padding(16.dp)) {
                            Text(text = "Miejsce: ${product.location}${product.sub}", style = MaterialTheme.typography.titleMedium)
                            Text(text = "EAN: ${product.ean}")
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun SaveProductDialog(initialEan: String, onDismiss: () -> Unit, onSave: (String, Int, String) -> Unit) {
    var eanInput by remember { mutableStateOf(initialEan) }
    var locationInput by remember { mutableStateOf("") }
    var subInput by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text(if (initialEan.isEmpty()) "Dodaj produkt" else "Zapisz dywan") },
        text = {
            Column {
                OutlinedTextField(
                    value = eanInput,
                    onValueChange = { eanInput = it },
                    label = { Text("Kod EAN / Indeks") },
                    enabled = initialEan.isEmpty()
                )
                Spacer(modifier = Modifier.height(8.dp))
                OutlinedTextField(
                    value = locationInput,
                    onValueChange = { locationInput = it },
                    label = { Text("Numer regału (np. 12)") }
                )
                OutlinedTextField(
                    value = subInput,
                    onValueChange = { subInput = it },
                    label = { Text("Sublokacja (np. A, B)") }
                )
            }
        },
        confirmButton = {
            Button(onClick = {
                val loc = locationInput.toIntOrNull() ?: 0
                onSave(eanInput.trim(), loc, subInput.uppercase().trim())
            }) { Text("Zapisz") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Anuluj") }
        }
    )
}

@Composable
fun CameraScannerView(onBarcodeScanned: (String) -> Unit, onClose: () -> Unit) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val cameraProviderFuture = remember { ProcessCameraProvider.getInstance(context) }

    Box(modifier = Modifier.fillMaxSize()) {
        AndroidView(
            modifier = Modifier.fillMaxSize(),
            factory = { ctx ->
                val previewView = PreviewView(ctx)
                val executor = ContextCompat.getMainExecutor(ctx)
                
                cameraProviderFuture.addListener({
                    val cameraProvider = cameraProviderFuture.get()
                    val preview = Preview.Builder().build().also {
                        it.setSurfaceProvider(previewView.surfaceProvider)
                    }

                    val options = BarcodeScannerOptions.Builder()
                        .setBarcodeFormats(Barcode.FORMAT_EAN_13, Barcode.FORMAT_EAN_8, Barcode.FORMAT_ALL_FORMATS)
                        .build()
                    val scanner = BarcodeScanning.getClient(options)

                    val imageAnalysis = ImageAnalysis.Builder()
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .build()

                    imageAnalysis.setAnalyzer(Executors.newSingleThreadExecutor()) { imageProxy ->
                        val mediaImage = imageProxy.image
                        if (mediaImage != null) {
                            @Suppress("UnsafeOptInUsageError")
                            val image = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)
                            scanner.process(image)
                                .addOnSuccessListener { barcodes ->
                                    barcodes.firstOrNull()?.rawValue?.let { barcodeValue ->
                                        onBarcodeScanned(barcodeValue)
                                    }
                                }
                                .addOnCompleteListener {
                                    imageProxy.close()
                                }
                        } else {
                            imageProxy.close()
                        }
                    }

                    try {
                        cameraProvider.unbindAll()
                        cameraProvider.bindToLifecycle(
                            lifecycleOwner,
                            CameraSelector.DEFAULT_BACK_CAMERA,
                            preview,
                            imageAnalysis
                        )
                    } catch (exc: Exception) {
                        Log.e("CameraScanner", "Błąd kamery", exc)
                    }
                }, executor)
                previewView
            }
        )
        Button(
            onClick = onClose,
            modifier = Modifier.align(Alignment.BottomCenter).padding(32.dp)
        ) {
            Text("Zamknij skaner")
        }
    }
}
