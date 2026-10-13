package pl.leroymerlin.expodywany038

import android.Manifest
import android.content.pm.PackageManager
import android.os.Bundle
import android.util.Log
import androidx.activity.ComponentActivity
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.annotation.OptIn
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Add
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import androidx.lifecycle.ViewModel
import com.google.firebase.firestore.FirebaseFirestore
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import java.util.concurrent.Executors

// --- 1. MODEL DANYCH ---
data class Product(
    val id: String = "",
    val location: Int = 0,
    val sub: String = "",
    val name: String = "Nowy Produkt",
    val reference: String = "",
    val ean: String = "",
    val updatedAt: Long = 0L
)

// --- 2. LOGIKA BAZY FIREBASE ---
class InventoryViewModel : ViewModel() {
    private val db = FirebaseFirestore.getInstance()
    private val collectionRef = db.collection("store_038").document("products").collection("items")

    private val _products = MutableStateFlow<List<Product>>(emptyList())
    val products: StateFlow<List<Product>> = _products

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

    fun saveScannedProduct(ean: String, location: Int, sub: String) {
        val product = Product(
            id = ean,
            ean = ean,
            location = location,
            sub = sub,
            updatedAt = System.currentTimeMillis()
        )
        collectionRef.document(ean).set(product)
    }
}

// --- 3. GŁÓWNA AKTYWNOŚĆ ---
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

// --- 4. INTERFEJS UŻYTKOWNIKA I SKANER ---
@Composable
fun MainScreen(viewModel: InventoryViewModel) {
    var showScanner by remember { mutableStateOf(false) }
    var scannedEan by remember { mutableStateOf<String?>(null) }
    
    val context = LocalContext.current
    var hasCamPermission by remember {
        mutableStateOf(ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED)
    }
    
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        hasCamPermission = granted
    }

    if (scannedEan != null) {
        // Okienko zapisu po zeskanowaniu
        SaveProductDialog(
            ean = scannedEan!!,
            onDismiss = { scannedEan = null },
            onSave = { loc, sub ->
                viewModel.saveScannedProduct(scannedEan!!, loc, sub)
                scannedEan = null
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
            floatingActionButton = {
                FloatingActionButton(onClick = {
                    if (!hasCamPermission) launcher.launch(Manifest.permission.CAMERA)
                    showScanner = true
                }) {
                    Icon(Icons.Filled.Add, contentDescription = "Skanuj")
                }
            }
        ) { padding ->
            ProductList(viewModel, Modifier.padding(padding))
        }
    }
}

@Composable
fun ProductList(viewModel: InventoryViewModel, modifier: Modifier = Modifier) {
    val products by viewModel.products.collectAsState()

    Column(modifier = modifier.padding(16.dp)) {
        Text(text = "Expo Dywany - Sklep 038", style = MaterialTheme.typography.headlineMedium)
        Spacer(modifier = Modifier.height(16.dp))
        
        if (products.isEmpty()) {
            Text("Brak produktów lub łączenie z bazą...")
        } else {
            LazyColumn {
                items(products) { product ->
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
fun SaveProductDialog(ean: String, onDismiss: () -> Unit, onSave: (Int, String) -> Unit) {
    var locationInput by remember { mutableStateOf("") }
    var subInput by remember { mutableStateOf("") }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("Zapisz dywan") },
        text = {
            Column {
                Text("Zeskanowano EAN: $ean")
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
                onSave(loc, subInput.uppercase())
            }) { Text("Zapisz") }
        },
        dismissButton = {
            TextButton(onClick = onDismiss) { Text("Anuluj") }
        }
    )
}

@OptIn(ExperimentalGetImage::class)
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
