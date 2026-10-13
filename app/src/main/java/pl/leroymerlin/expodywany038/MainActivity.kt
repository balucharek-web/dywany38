package pl.leroymerlin.expodywany038

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.ViewModel
import com.google.firebase.firestore.FirebaseFirestore
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow

// --- 1. MODEL DANYCH ---
data class Product(
    val id: String = "",
    val location: Int = 0,
    val sub: String = "",
    val name: String = "Brak produktu",
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
}

// --- 3. GŁÓWNY EKRAN APLIKACJI ---
class MainActivity : ComponentActivity() {
    private val viewModel = InventoryViewModel()

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                Surface(modifier = Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                    ProductList(viewModel)
                }
            }
        }
    }
}

@Composable
fun ProductList(viewModel: InventoryViewModel) {
    val products by viewModel.products.collectAsState()

    Column(modifier = Modifier.padding(16.dp)) {
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
                            Text(text = "Nazwa: ${product.name}")
                            Text(text = "Ref: ${product.reference} | EAN: ${product.ean}")
                        }
                    }
                }
            }
        }
    }
}
